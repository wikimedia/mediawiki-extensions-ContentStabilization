<?php

namespace MediaWiki\Extension\ContentStabilization;

use Config;
use GlobalVarConfig;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use ParserFactory;
use RepoGroup;
use Title;
use Wikimedia\Rdbms\ILoadBalancer;

class InclusionManager {

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var RepoGroup */
	private $repoGroup;

	/** @var InclusionMode|null|false */
	private $inclusionMode = false;

	/** @var GlobalVarConfig */
	private $config;

	/** @var ParserFactory */
	private $parserFactory;

	/** @var array */
	private $inclusionModes;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param WikiPageFactory $wikiPageFactory
	 * @param RevisionLookup $revisionLookup
	 * @param RepoGroup $repoGroup
	 * @param GlobalVarConfig $config
	 * @param ParserFactory $parserFactory
	 * @param array $inclusionModes
	 */
	public function __construct(
		ILoadBalancer $loadBalancer, WikiPageFactory $wikiPageFactory,
		RevisionLookup $revisionLookup, RepoGroup $repoGroup, Config $config,
		ParserFactory $parserFactory, array $inclusionModes
	) {
		$this->loadBalancer = $loadBalancer;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->revisionLookup = $revisionLookup;
		$this->repoGroup = $repoGroup;
		$this->config = $config;
		$this->parserFactory = $parserFactory;
		$this->inclusionModes = $inclusionModes;
	}

	/**
	 * Store state of transcluded resources at this time
	 *
	 * @param RevisionRecord $revision
	 *
	 * @return array
	 */
	public function stabilizeInclusions( RevisionRecord $revision ): array {
		$inclusions = $this->getCurrentInclusions( $revision->getPageAsLinkTarget() );
		return [
			'transclusions' => $this->storeTransclusions( $revision, $inclusions['transclusions'] ),
			'images' => $this->storeImages( $revision, $inclusions['images'] ),
		];
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return bool
	 */
	public function removeStableInclusionsForRevision( RevisionRecord $revisionRecord ): bool {
		$dbw = $this->loadBalancer->getConnectionRef( DB_PRIMARY );
		$r1 = $dbw->delete(
			'stable_transclusions',
			[ 'st_revision' => $revisionRecord->getId() ],
			__METHOD__
		);
		$r2 = $dbw->delete(
			'stable_file_transclusions',
			[ 'sft_revision' => $revisionRecord->getId() ],
			__METHOD__
		);
		return $r1 && $r2;
	}

	/**
	 * @param PageIdentity $page
	 *
	 * @return bool
	 */
	public function removeStableInclusionsForPage( PageIdentity $page ): bool {
		$dbw = $this->loadBalancer->getConnectionRef( DB_PRIMARY );
		$r1 = $dbw->delete(
			'stable_transclusions',
			[ 'st_page' => $page->getId() ],
			__METHOD__
		);
		$r2 = $dbw->delete(
			'stable_file_transclusions',
			[ 'sft_page' => $page->getId() ],
			__METHOD__
		);

		return $r1 && $r2;
	}

	/**
	 * Retrieve stabilized inclusions for given revision
	 *
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return array[]
	 */
	public function getStableInclusions( RevisionRecord $revisionRecord ): array {
		$inclusions = [
			'transclusions' => $this->retrieveTransclusions( $revisionRecord ),
			'images' => $this->retrieveImages( $revisionRecord ),
		];
		if ( $this->getInclusionMode() === null ) {
			return $inclusions;
		}
		return $this->getInclusionMode()->stabilizeInclusions( $inclusions, $revisionRecord );
	}

	/**
	 * Retrieve current stabilized inclusions for given revision
	 * Difference to `getStableInclusions` is that this method will not base its result
	 * on the versions of the inclusions that were stable at the time of the revision,
	 * but will look up the current version of the inclusions.
	 *
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return array|array[]
	 */
	public function getCurrentStabilizedInclusions( RevisionRecord $revisionRecord ) {
		$current = $this->getCurrentInclusions( $revisionRecord->getPageAsLinkTarget() );
		if ( $this->getInclusionMode() === null ) {
			return $current;
		}
		return $this->getInclusionMode()->stabilizeInclusions( $current, $revisionRecord );
	}

	/**
	 * @return InclusionMode|null
	 */
	private function getInclusionMode(): ?InclusionMode {
		if ( $this->inclusionMode === false ) {
			$enabled = $this->config->get( 'InclusionMode' );
			if ( $enabled && isset( $this->inclusionModes[$enabled] ) ) {
				$this->inclusionMode = $this->inclusionModes[$enabled];
			} else {
				$this->inclusionMode = null;
			}
		}
		return $this->inclusionMode;
	}

	/**
	 * Get the latest revisions of inclusions at this time
	 *
	 * @param LinkTarget $target
	 *
	 * @return array[]
	 */
	private function getCurrentInclusions( LinkTarget $target ): array {
		$page = $this->wikiPageFactory->newFromLinkTarget( $target );
		$parserOptions = $page->makeParserOptions( 'canonical' );
		$parser = $this->parserFactory->getMainInstance();
		if ( !$parser ) {
			return [];
		}
		$parserOutput = $parser->parse(
			$page->getContent()->getWikitextForTransclusion(), $page->getTitle(), $parserOptions,
			true, true, $page->getTitle()->getLatestRevID()
		);
		$transclusions = $parserOutput->getTemplates();
		$images = $parserOutput->getImages();

		$res = [ 'transclusions' => [], 'images' => [] ];
		foreach ( $transclusions as $nsId => $pages ) {
			foreach ( $pages as $dbKey => $pageId ) {
				if ( $dbKey === $target->getDBkey() && $target->getNamespace() === $nsId ) {
					// For some reason, getTemplates returns the current page as a transclusion
					continue;
				}
				$latest = $this->revisionLookup->getRevisionByPageId( $pageId );
				if ( !$latest ) {
					// Page doesn't exist (or something worse)
					continue;
				}
				$res['transclusions'][] = [
					'revision' => $latest->getId(),
					'namespace' => $nsId,
					'title' => $dbKey,
				];
			}
		}

		foreach ( $images as $name => $id ) {
			$image = $this->repoGroup->getLocalRepo()->findFile( $name );
			if ( !$image ) {
				// Image doesn't exist (or something worse)
				continue;
			}
			$res['images'][] = [
				'revision' => $image->getTitle()->getLatestRevID( Title::READ_LATEST ),
				'name' => $image->getName(),
				'timestamp' => $image->getTimestamp(),
				'sha1' => $image->getSha1(),
			];
		}

		return $res;
	}

	/**
	 * @param RevisionRecord $main
	 * @param array $transclusions
	 *
	 * @return array
	 */
	private function storeTransclusions( RevisionRecord $main, array $transclusions ): array {
		$data = [];
		foreach ( $transclusions as $item ) {
			$data[] = [
				'st_revision' => $main->getId(),
				'st_page' => $main->getPageId(),
				'st_transclusion_revision' => $item['revision'],
				'st_transclusion_namespace' => $item['namespace'],
				'st_transclusion_title' => $item['title'],
			];
		}
		$dbw = $this->loadBalancer->getConnectionRef( DB_PRIMARY );
		// Avoid complex comparisons, just delete and re-insert
		$dbw->delete( 'stable_transclusions', [ 'st_revision' => $main->getId() ], __METHOD__ );
		// Ignore errors, as final data returned will be what is actually inserted
		$dbw->insert( 'stable_transclusions', $data, __METHOD__, [ 'IGNORE' ] );

		// Additional query, yes, but retrieves the actual data, and its formatted
		return $this->retrieveTransclusions( $main );
	}

	/**
	 * @param RevisionRecord $main
	 * @param array $images
	 *
	 * @return array
	 */
	private function storeImages( RevisionRecord $main, array $images ): array {
		$data = [];
		foreach ( $images as $image ) {
			$data[] = [
				'sft_revision' => $main->getId(),
				'sft_page' => $main->getPageId(),
				'sft_file_revision' => $image['revision'],
				'sft_file_name' => $image['name'],
				'sft_file_timestamp' => $image['timestamp'],
				'sft_file_sha1' => $image['sha1'],
			];
		}
		$dbw = $this->loadBalancer->getConnectionRef( DB_PRIMARY );
		// Avoid complex comparisons, just delete and re-insert
		$dbw->delete( 'stable_file_transclusions', [ 'sft_revision' => $main->getId() ], __METHOD__ );
		// Ignore errors, as final data returned will be what is actually inserted
		$dbw->insert( 'stable_file_transclusions', $data, __METHOD__, [ 'IGNORE' ] );

		// Additional query, yes, but retrieves the actual data, and its formatted
		return $this->retrieveImages( $main );
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return array
	 */
	private function retrieveTransclusions( RevisionRecord $revisionRecord ): array {
		$db = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		$res = $db->select(
			'stable_transclusions',
			[ 'st_transclusion_revision', 'st_transclusion_namespace', 'st_transclusion_title' ],
			[ 'st_revision' => $revisionRecord->getId() ],
			__METHOD__
		);

		$transclusions = [];
		foreach ( $res as $row ) {
			$transclusions[] = [
				'revision' => (int)$row->st_transclusion_revision,
				'namespace' => (int)$row->st_transclusion_namespace,
				'title' => $row->st_transclusion_title,
			];
		}
		return $transclusions;
	}

	/**
	 * @param RevisionRecord $main
	 *
	 * @return array
	 */
	private function retrieveImages( RevisionRecord $main ): array {
		$db = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		$res = $db->select(
			'stable_file_transclusions',
			[ 'sft_file_revision', 'sft_file_name', 'sft_file_timestamp', 'sft_file_sha1' ],
			[ 'sft_revision' => $main->getId() ],
			__METHOD__
		);

		$images = [];
		foreach ( $res as $row ) {
			$images[] = [
				'revision' => (int)$row->sft_file_revision,
				'name' => $row->sft_file_name,
				'timestamp' => $row->sft_file_timestamp,
				'sha1' => $row->sft_file_sha1,
			];
		}
		return $images;
	}

	/**
	 * Return changed inclusions if not in sync, empty array otherwise
	 *
	 * @param StablePoint $point
	 *
	 * @return array
	 */
	public function getSyncDifference( StablePoint $point ): array {
		if ( $this->getInclusionMode() && !$this->getInclusionMode()->canBeOutOfSync( $point->getRevision() ) ) {
			return [];
		}
		$stableInclusions = $point->getInclusions();
		$latestInclusions = $this->getCurrentStabilizedInclusions( $point->getRevision() );
		$tc = $this->compareTransclusions( $latestInclusions['transclusions'], $stableInclusions['transclusions'] );
		$ic = $this->compareImages( $latestInclusions['images'], $stableInclusions['images'] );
		$untracked = $this->getUntrackedInclusions( $latestInclusions, $stableInclusions );

		$res = [];
		if ( !empty( $tc ) ) {
			$res['transclusions'] = $tc;
		}
		if ( !empty( $ic ) ) {
			$res['images'] = $ic;
		}
		if ( !empty( $untracked ) ) {
			$res['untracked'] = $untracked;
		}
		return $res;
	}

	/**
	 * @param array $latest
	 * @param array $stable
	 *
	 * @return array empty if same
	 */
	private function compareTransclusions( array $latest, array $stable ): array {
		return $this->rawCompare( $latest, $stable, [ 'namespace', 'title', 'revision' ] );
	}

	/**
	 * @param array $latest
	 * @param array $stable
	 *
	 * @return array empty if same
	 */
	private function compareImages( array $latest, array $stable ): array {
		return $this->rawCompare( $latest, $stable, [ 'name', 'timestamp' ] );
	}

	/**
	 * @param array $a
	 * @param array $b
	 * @param array $keys
	 *
	 * @return array
	 */
	private function rawCompare( array $a, array $b, array $keys ): array {
		$a = $this->simplifyInclusionObjects( $a, $keys );
		$b = $this->simplifyInclusionObjects( $b, $keys );

		$diff = array_diff_key( $a, $b );
		if ( !empty( $diff ) ) {
			return array_values( $diff );
		}
		return [];
	}

	/**
	 * @param array $inclusions
	 * @param array $fields
	 *
	 * @return array
	 */
	private function simplifyInclusionObjects( array $inclusions, array $fields ): array {
		$simplified = [];
		foreach ( $inclusions as $inclusion ) {
			$relevantKeys = array_intersect_key( $inclusion, array_flip( $fields ) );
			$simpleKey = implode( '|', array_values( $relevantKeys ) );
			$simplified[$simpleKey] = $inclusion;
		}
		return $simplified;
	}

	/**
	 * @param array $latestInclusions
	 * @param array $stableInclusions
	 *
	 * @return array
	 */
	private function getUntrackedInclusions( array $latestInclusions, array $stableInclusions ): array {
		return array_values( array_diff(
			$this->simplifyForUntrackedCheck( $latestInclusions ),
			$this->simplifyForUntrackedCheck( $stableInclusions )
		) );
	}

	/**
	 * @param array $inclusions
	 *
	 * @return array
	 */
	private function simplifyForUntrackedCheck( array $inclusions ): array {
		$simplified = [];
		foreach ( $inclusions['transclusions'] as $inclusion ) {
			$simplified[] = 't|' . $inclusion['namespace'] . '|' . $inclusion['title'];
		}
		foreach ( $inclusions['images'] as $inclusion ) {
			$simplified[] = 'i|' . $inclusion['name'];
		}

		return $simplified;
	}
}
