<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use File;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StableFilePoint;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Extension\DrawioEditor\Hook\DrawioGetFileHook;
use Title;
use User;

class StabilizeDrawioFiles implements DrawioGetFileHook {

	/** @var StabilizationLookup */
	private $lookup;

	/**
	 * @param StabilizationLookup $lookup
	 */
	public function __construct( StabilizationLookup $lookup ) {
		$this->lookup = $lookup;
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
	 * @inheritDoc
	 */
	public function onDrawioGetFile(
		File &$file, &$latestIsStable, User $user, bool &$isNotApproved, File &$displayFile
	) {
		if ( !$this->lookup->isStabilizationEnabled( $file->getTitle()->toPageIdentity() ) ) {
			return;
		}
		if ( $this->lookup->canUserSeeUnstable( $user ) ) {
			return;
		}
		$stable = $this->getStable( $file->getTitle() );
		if ( !$stable || !( $stable instanceof StableFilePoint ) ) {
			if ( $this->lookup->isFirstUnstableAllowed() ) {
				return;
			}
			// Cannot show anything
			$isNotApproved = true;
			$latestIsStable = false;
			$displayFile = null;
			return;
		}

		if ( $stable->getRevision()->isCurrent() ) {
			$latestIsStable = true;
			return;
		}
		$latestIsStable = false;
		$file = $stable->getFile();
		$displayFile = $file;
	}

}
