<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use Config;
use File;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StableFilePoint;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Revision\RevisionRecord;
use Title;

class StabilizeSearchIndex {

	/** @var StabilizationLookup */
	private $lookup;

	/** @var Config */
	private $config;

	/**
	 * @param StabilizationLookup $lookup
	 * @param Config $config
	 */
	public function __construct( StabilizationLookup $lookup, Config $config ) {
		$this->lookup = $lookup;
		$this->config = $config;
	}

	/**
	 * @param Title $title
	 * @param RevisionRecord|null &$revision
	 *
	 * @return void
	 */
	public function onBSExtendedSearchWikipageFetchRevision( Title $title, ?RevisionRecord &$revision ) {
		if ( !$this->shouldIndexStableOnly() || !$this->lookup->isStabilizationEnabled( $title ) ) {
			return;
		}
		$stable = $this->getStable( $title );
		if ( !$stable ) {
			if ( $this->lookup->isFirstUnstableAllowed() ) {
				return;
			}
			$revision = null;
			return;
		}
		$revision = $stable->getRevision();
	}

	/**
	 * @param File|null &$file
	 *
	 * @return void
	 */
	public function onBSExtendedSearchRepoFileGetRepoFile( ?File &$file ) {
		if (
			!$this->shouldIndexStableOnly() ||
			!$this->lookup->isStabilizationEnabled( $file->getTitle()->toPageIdentity() )
		) {
			return;
		}
		$stable = $this->getStable( $file->getTitle() );
		if ( !$stable || !( $stable instanceof StableFilePoint ) ) {
			if ( $this->lookup->isFirstUnstableAllowed() ) {
				return;
			}
			$file = null;
			return;
		}
		$file = $stable->getFile();
	}

	/**
	 * @param Title $title
	 *
	 * @return StablePoint|null
	 */
	private function getStable( Title $title ): ?StablePoint {
		return $this->lookup->getLastStablePoint( $title->toPageIdentity() );
	}

	/**
	 * @return bool
	 */
	private function shouldIndexStableOnly(): bool {
		return $this->config->get( 'BlueSpiceExtendedSearchIndexOnlyStable' );
	}
}
