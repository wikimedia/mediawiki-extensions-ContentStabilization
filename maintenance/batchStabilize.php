<?php

use MediaWiki\Extension\ContentStabilization\StabilizationBot;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class BatchStabilize extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->addArg( 'pages', 'File with pages to stabilize, newline separate', false );
		$this->addOption( 'namespace', 'Namespace to stabilize', false, true );
		$this->addOption( 'user', 'Stabilization actor', false, true );
		$this->addOption( 'comment', 'Stabilization comment', false, true );
		$this->addOption( 'verbose', 'Show exceptions' );
	}

	public function execute() {
		$pages = $this->selectPages();
		if ( empty( $pages ) ) {
			$this->output( "No pages to stabilize\n" );
			return;
		}
		$this->output( "Stabilizing " . count( $pages ) . " pages\n" );

		$user = $this->getUser();
		$comment = $this->getComment();

		/** @var \MediaWiki\Extension\ContentStabilization\ContentStabilizer $stabilizer */
		$stabilizer = MediaWikiServices::getInstance()->getService( 'ContentStabilization.Stabilizer' );
		$revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();
		$success = $fail = 0;
		foreach ( $pages as $page ) {
			try {
				$revision = $revisionLookup->getRevisionByPageId( $page->page_id );
				if ( !$revision ) {
					throw new Exception( "No revision found for page {$page->page_title}" );
				}
				$res = $stabilizer->addStablePoint( $revision, $user, $comment );
				if ( $res instanceof StablePoint ) {
					$success++;
				} else {
					throw new Exception( "Failed to stabilize page {$page->page_title}" );
				}
			} catch ( Exception $ex ) {
				if ( $this->hasOption( 'verbose' ) ) {
					$this->error( "{$page->page_title}: {$ex->getMessage()}" );
				}

				$fail++;
			}
		}
		$this->output( "Stabilized $success pages, failed to stabilize $fail pages\n" );
	}

	/**
	 * @return string
	 */
	public function getComment(): string {
		return $this->getOption( 'comment', 'Batch stabilization' );
	}

	/**
	 * @return Authority
	 */
	private function getUser(): Authority {
		$user = null;
		if ( $this->hasOption( 'user' ) ) {
			$username = $this->getOption( 'user' );
			if ( $username ) {
				$user = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $username );
			}
			if ( !$user ) {
				$this->fatalError( "Specified user is invalid" );
			}
		} else {
			$user = new StabilizationBot();
		}

		if ( !$user ) {
			$this->fatalError( "Failed to create user" );
		}

		return $user;
	}

	/**
	 * @return array|ResultWrapper
	 */
	private function selectPages() {
		$pages = $this->readInPagesFromArg();
		if ( is_array( $pages ) ) {
			return $pages;
		}
		if ( $this->hasOption( 'namespace' ) ) {
			$namespace = $this->getOption( 'namespace' );
			if ( !is_numeric( $namespace ) ) {
				$namespace = MediaWikiServices::getInstance()->getNamespaceInfo()->getCanonicalIndex( $namespace );
			}
			if ( $namespace === false ) {
				$this->fatalError( "Invalid namespace" );
			}
			return $this->getDB( DB_REPLICA )
				->select( 'page', [ 'page_id', 'page_title' ], [ 'page_namespace' => $namespace ] );
		}
		return [];
	}

	/**
	 * @return array|null
	 */
	private function readInPagesFromArg() {
		$oldCwd = getcwd();
		chdir( $oldCwd );

		$file = null;
		if ( $this->hasArg( 0 ) ) {
			$file = fopen( $this->getArg( 0 ), 'r' );
		}

		if ( !$file ) {
			// No pages file specified
			return null;
		}
		$pages = [];
		for ( $linenum = 1; !feof( $file ); $linenum++ ) {
			$line = trim( fgets( $file ) );
			if ( $line == '' ) {
				continue;
			}
			$title = MediaWikiServices::getInstance()->getTitleFactory()->newFromText( $line );
			if ( !$title ) {
				$this->error( "Invalid page title: " . $line );
				continue;
			}
			$pages[] = (object)[ 'page_id' => $title->getArticleID(), 'page_title' => $title->getPrefixedText() ];
		}

		return $pages;
	}
}

$maintClass = BatchStabilize::class;
require_once RUN_MAINTENANCE_IF_MAIN;
