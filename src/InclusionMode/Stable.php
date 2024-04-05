<?php

namespace MediaWiki\Extension\ContentStabilization\InclusionMode;

use Config;
use MediaWiki\Extension\ContentStabilization\InclusionMode;
use MediaWiki\Extension\ContentStabilization\StableFilePoint;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Extension\ContentStabilization\Storage\StablePointStore;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use RepoGroup;
use TitleFactory;

class Stable implements InclusionMode {
	/** @var RevisionLookup */
	private $revisionLookup;
	/** @var StablePointStore */
	private $store;
	/** @var RepoGroup */
	private $repoGroup;
	/** @var TitleFactory */
	private $titleFactory;
	/** @var array */
	private $enabledNamespaces;

	/**
	 * @param RevisionLookup $revisionLookup
	 * @param StablePointStore $store
	 * @param RepoGroup $repoGroup
	 * @param TitleFactory $titleFactory
	 * @param Config $config
	 */
	public function __construct(
		RevisionLookup $revisionLookup, StablePointStore $store, RepoGroup $repoGroup,
		TitleFactory $titleFactory, Config $config
	) {
		$this->revisionLookup = $revisionLookup;
		$this->store = $store;
		$this->repoGroup = $repoGroup;
		$this->titleFactory = $titleFactory;
		$this->enabledNamespaces = $config->get( 'EnabledNamespaces' );
	}

	/**
	 * @inheritDoc
	 */
	public function canBeOutOfSync( ?RevisionRecord $revisionToCheckFor = null ): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function stabilizeInclusions( array $inclusions, RevisionRecord $mainRevision ): array {
		$viewingLatest = $mainRevision->isCurrent() || !$this->hasNewerStable( $mainRevision );
		foreach ( $inclusions as $type => &$inclusionArray ) {
			foreach ( $inclusionArray as &$inclusion ) {
				if ( $type === 'transclusions' ) {
					$page = $this->titleFactory->makeTitle( $inclusion['namespace'], $inclusion['title'] );
				} elseif ( $type === 'images' ) {
					$page = $this->titleFactory->makeTitle( NS_FILE, $inclusion['name'] );
				} else {
					continue;
				}
				if ( !$page->exists() ) {
					continue;
				}
				if ( !in_array( $page->getNamespace(), $this->enabledNamespaces ) ) {
					continue;
				}
				// In case user is viewing the latest revision of the page, or latest stable revision,
				// show the latest stable version of includes.
				// Otherwise, limit to the last stable version before the freezing point
				// (if user is viewing old version of the page, show transclusions as they were at that time)

				$revLimit = !$viewingLatest ? $inclusion['revision'] : 0;
				$conds = [
					'sp_page' => $page->getArticleID()
				];
				if ( $revLimit > 0 ) {
					$conds[] = 'sp_revision <= ' . $revLimit;
				}
				$stableInclusion = $this->store->getLatestMatchingPoint( $conds );
				// If there is no stable version,
				// use revision that was current at the time of $mainRevision's stabilization
				if ( $type === 'transclusions' ) {
					if ( $stableInclusion instanceof StablePoint ) {
						$revisionRecord = $stableInclusion->getRevision();
					} else {
						$revisionRecord = $this->revisionLookup->getRevisionById( $inclusion['revision'] );
					}
					if ( !$revisionRecord ) {
						continue;
					}

					// Set stabilized revision to the inclusion
					$inclusion['revision'] = $revisionRecord->getId();
				}

				if ( $type === 'images' ) {
					if ( $stableInclusion instanceof StableFilePoint ) {
						$file = $stableInclusion->getFile();
					} else {
						// Get the file at the time of this revision
						$file = $this->repoGroup->findFile(
							$inclusion['name'], [ 'time' => $inclusion['timestamp'] ]
						);
					}

					if ( !$file ) {
						// Do not show file at all
						$inclusion['revision'] = 0;
						$inclusion['timestamp'] = 0;
						$inclusion['sha1'] = '';
						continue;
					}
					$inclusion['timestamp'] = $file->getTimestamp();
					$inclusion['sha1'] = $file->getSha1();
				}
			}
		}
		return $inclusions;
	}

	/**
	 * @param RevisionRecord $mainRevision
	 *
	 * @return bool
	 */
	private function hasNewerStable( RevisionRecord $mainRevision ): bool {
		$stable = $this->store->getLatestMatchingPoint( [
			'sp_page' => $mainRevision->getPageId(),
			'sp_revision > ' . $mainRevision->getId(),
		] );
		return $stable instanceof StablePoint;
	}
}
