<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ContentStabilization\StabilizationBot;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StableView;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class SyncResources extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->addOption( 'namespace', 'Only sync on page in particular namespace. Namespace ID.' );
		$this->addOption( 'untracked-only', 'Only sync untracked resources', false, false, 'u' );
		$this->addArg( 'resources', 'Newline-separated list of resource pages to sync. For files, specify file page' );
	}

	public function execute() {
		$this->output( "\033[0;31m!!! THIS SCRIPT CAN CAUSE IRREVERSIBLE CHANGES TO THE DB !!!\n" );
		$this->output( "It is recommended to make a database backup before executing on productive systems\033[0m\n" );
		$this->countDown( 5 );

		// Set user with permissions to see all as the context user
		RequestContext::getMain()->setUser( ( new StabilizationBot() )->getUser() );
		$titles = $this->findPages();
		if ( empty( $titles ) ) {
			$this->error( "No pages found" );
			return;
		}
		$views = $this->getViews( $titles );
		if ( empty( $views ) ) {
			$this->error( "No out of sync pages found\n" );
			return;
		}
		$this->output( "Found " . count( $views ) . " out of sync pages\n" );
		$resources = $this->extractResources();
		$this->doSync( $views, $resources );
	}

	/**
	 * @return Title[]
	 */
	private function findPages(): array {
		$enabledNamespaces = MediaWikiServices::getInstance()->getMainConfig()->get(
			'ContentStabilizationEnabledNamespaces'
		);
		$namespace = $this->getOption( 'namespace' );
		if ( $namespace !== null && !in_array( $namespace, $enabledNamespaces ) ) {
			return [];
		}
		$db = $this->getDB( DB_REPLICA );
		$conds = [];
		if ( $namespace !== null ) {
			$conds['page_namespace'] = $namespace;
		} else {
			$conds[] = 'page_namespace IN (' . $db->makeList( $enabledNamespaces ) . ')';
		}
		$this->output( "Finding all pages..." );
		$res = $db->select(
			'page',
			[ 'page_id', 'page_namespace', 'page_title' ],
			$conds,
			__METHOD__
		);

		$titles = [];
		foreach ( $res as $row ) {
			$titles[] = Title::newFromRow( $row );
		}
		$this->output( "done\n" );
		return $titles;
	}

	/**
	 * @param Title[] $titles
	 *
	 * @return StableView[]
	 */
	private function getViews( array $titles ): array {
		/** @var StabilizationLookup $lookup */
		$lookup = MediaWikiServices::getInstance()->getService( 'ContentStabilization.Lookup' );
		$this->output( "Getting views for " . count( $titles ) . " pages...\n" );
		$views = [];
		$cnt = 0;
		foreach ( $titles as $title ) {
			if ( !$title ) {
				continue;
			}
			$cnt++;
			if ( $cnt % 100 === 0 ) {
				$this->output( "Checked $cnt views...\n" );
			}
			$view = $lookup->getStableView( $title->toPageIdentity(), null, [ 'forceUnstable' => true ] );
			if (
				!$view ||
				$view->getStatus() !== StableView::STATE_IMPLICIT_UNSTABLE ||
				!$view->getOutOfSyncInclusions()
			) {
				continue;
			}
			$views[] = $view;
		}
		return $views;
	}

	/**
	 * @return array
	 */
	private function extractResources(): array {
		$resources = [];
		$lines = explode( "\n", file_get_contents( $this->getArg( 0 ) ) );
		$output = [];
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( !$line ) {
				continue;
			}
			$title = Title::newFromText( $line );
			if ( !$title ) {
				$this->error( "Invalid title: $line\n" );
				continue;
			}
			$output[] = $title->getPrefixedText();
			if ( $title->getNamespace() === NS_FILE ) {
				$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $title );
				if ( !$file ) {
					continue;
				}
				$resources[] = [
					'type' => 'file',
					'sft_file_name' => $title->getDBkey(),
					'sft_file_timestamp' => $file->getTimestamp(),
					'sft_file_sha1' => $file->getSha1(),
				];
			} else {
				$resources[] = [
					'type' => 'transclusion',
					'st_transclusion_revision' => $title->getLatestRevID(),
					'st_transclusion_title' => $title->getDBkey(),
					'st_transclusion_namespace' => $title->getNamespace()
				];
			}
		}
		if ( !empty( $output ) ) {
			$this->output( "Syncing resources:\n" . implode( "\n", $output ) . "\n-------\n" );
		}
		return $resources;
	}

	/**
	 * @param StableView[] $views
	 * @param array $resources
	 *
	 * @return void
	 */
	private function doSync( array $views, array $resources ) {
		$onlyUntracked = $this->hasOption( 'untracked-only' );
		if ( $onlyUntracked ) {
			$this->output( "Only syncing untracked resources\n" );
		}
		$imageCount = $transclusionCount = 0;
		foreach ( $views as $view ) {
			$outOfSync = $view->getOutOfSyncInclusions();
			$didSyncTransclusions = $this->syncTransclusions( $view, $outOfSync, $resources, $onlyUntracked );
			$didSyncImages = $this->syncImages( $view, $outOfSync, $resources, $onlyUntracked );
			if ( $didSyncTransclusions || $didSyncImages ) {
				$this->output(
					"Synced:" . $view->getPage()->getNamespace() . ':' . $view->getPage()->getDBkey() . "\n"
				);
			}
			$transclusionCount += $didSyncTransclusions;
			$imageCount += $didSyncImages;
		}

		if ( $transclusionCount || $imageCount ) {
			$this->output( "Synced $transclusionCount transclusions and $imageCount images\n" );
		} else {
			$this->output( "No resources to sync\n" );
		}
	}

	/**
	 * @param StableView $view
	 * @param array $inclusions
	 * @param array $resources
	 * @param bool $onlyUntracked
	 *
	 * @return int number of updated transclusions
	 */
	private function syncTransclusions(
		StableView $view, array $inclusions, array $resources, bool $onlyUntracked
	): int {
		$insertData = [];
		$untracked = $this->getUntrackedResources( $inclusions, $resources, 't' );
		foreach ( $untracked as $untrackedResource ) {
			$insertData[] = array_merge( [
				'st_revision' => $view->getRevision()->getId(),
				'st_page' => $view->getPage()->getId(),
			], $untrackedResource );
		}

		if ( !$onlyUntracked ) {
			foreach ( $inclusions['transclusions'] ?? [] as $transclusion ) {
				$title = MediaWikiServices::getInstance()->getTitleFactory()->makeTitle(
					$transclusion['namespace'], $transclusion['title']
				);
				$relevantResource = $this->getResource( $title, $resources );
				if ( !$relevantResource ) {
					continue;
				}
				$insertData[] = array_merge( [
					'st_revision' => $view->getRevision()->getId(),
					'st_page' => $view->getPage()->getId(),
				], $relevantResource );
			}
		}

		return $this->doInsert( 'stable_transclusions', $insertData, __METHOD__ );
	}

	/**
	 * @param StableView $view
	 * @param array $inclusions
	 * @param array $resources
	 * @param bool $onlyUntracked
	 *
	 * @return int number of updated images
	 */
	private function syncImages( StableView $view, array $inclusions, array $resources, bool $onlyUntracked ): int {
		$insertData = [];
		$untracked = $this->getUntrackedResources( $inclusions, $resources, 'i' );
		foreach ( $untracked as $untrackedResource ) {
			$insertData[] = array_merge( [
				'sft_revision' => $view->getRevision()->getId(),
				'sft_page' => $view->getPage()->getId(),
				'sft_file_revision' => -1,
			], $untrackedResource );
		}
		if ( !$onlyUntracked ) {
			foreach ( $inclusions['images'] ?? [] as $image ) {
				$title = MediaWikiServices::getInstance()->getTitleFactory()->makeTitle(
					NS_FILE, $image['name']
				);
				$relevantResource = $this->getResource( $title, $resources );
				if ( !$relevantResource ) {
					continue;
				}
				$insertData[] = array_merge( [
					'sft_revision' => $view->getRevision()->getId(),
					'sft_page' => $view->getPage()->getId(),
					'sft_file_revision' => -1,
				], $relevantResource );
			}
		}

		return $this->doInsert( 'stable_file_transclusions', $insertData, __METHOD__ );
	}

	/**
	 * @param string $type
	 * @param array $inclusions
	 *
	 * @return Title[]
	 */
	private function getUntrackedTitlesForType( string $type, array $inclusions ): array {
		$titles = [];
		foreach ( $inclusions['untracked'] ?? [] as $untrackedInclusion ) {
			$bits = explode( '|', $untrackedInclusion );
			if ( $bits[0] !== $type ) {
				continue;
			}
			if ( $type === 't' ) {
				$titles[] = MediaWikiServices::getInstance()->getTitleFactory()->makeTitle( $bits[1], $bits[2] );
			} elseif ( $type === 'i' ) {
				$titles[] = MediaWikiServices::getInstance()->getTitleFactory()->makeTitle( NS_FILE, $bits[1] );
			}
		}
		return $titles;
	}

	/**
	 * @param Title $title
	 * @param array $resources
	 *
	 * @return array|null
	 */
	private function getResource( Title $title, array $resources ): ?array {
		foreach ( $resources as $resource ) {
			$resourceType = $resource['type'] ?? null;
			unset( $resource['type'] );
			if ( $resourceType === 'file' && $title->getNamespace() === NS_FILE ) {
				if ( $title->getDBkey() === $resource['sft_file_name'] ) {
					return $resource;
				}
			} elseif ( $resourceType === 'transclusion' && $title->getNamespace() !== NS_FILE ) {
				if (
					$title->getNamespace() === $resource['st_transclusion_namespace'] &&
					$title->getDBkey() === $resource['st_transclusion_title']
				) {
					return $resource;
				}
			}
		}
		return null;
	}

	/**
	 * @param array $inclusions
	 * @param array $resources
	 * @param string $type
	 *
	 * @return array
	 */
	private function getUntrackedResources( array $inclusions, array $resources, string $type ): array {
		$untracked = [];
		foreach ( $this->getUntrackedTitlesForType( $type, $inclusions ) as $untrackedTitle ) {
			$relevantResource = $this->getResource( $untrackedTitle, $resources );
			if ( !$relevantResource ) {
				continue;
			}
			$untracked[] = $relevantResource;
		}
		return $untracked;
	}

	/**
	 * @param string $table
	 * @param array $insertData
	 * @param string $method
	 *
	 * @return int
	 */
	private function doInsert( string $table, array $insertData, string $method ): int {
		$res = true;
		if ( $insertData ) {
			$res = $this->getDB( DB_PRIMARY )->insert(
				$table, $insertData, $method, [ 'IGNORE' ]
			);
		}

		return $res ? count( $insertData ) : 0;
	}
}

$maintClass = SyncResources::class;
require_once RUN_MAINTENANCE_IF_MAIN;
