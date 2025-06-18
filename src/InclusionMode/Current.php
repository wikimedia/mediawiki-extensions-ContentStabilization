<?php

namespace MediaWiki\Extension\ContentStabilization\InclusionMode;

use MediaWiki\Extension\ContentStabilization\InclusionMode;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\TitleFactory;
use RepoGroup;

class Current implements InclusionMode {
	/** @var RevisionLookup */
	private $revisionLookup;
	/** @var RepoGroup */
	private $repoGroup;
	/** @var TitleFactory */
	private $titleFactory;
	/** @var HookContainer */
	private $hookContainer;

	/**
	 * @param RevisionLookup $revisionLookup
	 * @param RepoGroup $repoGroup
	 * @param TitleFactory $titleFactory
	 * @param HookContainer $hookContainer
	 */
	public function __construct(
		RevisionLookup $revisionLookup, RepoGroup $repoGroup, TitleFactory $titleFactory, HookContainer $hookContainer
	) {
		$this->revisionLookup = $revisionLookup;
		$this->repoGroup = $repoGroup;
		$this->titleFactory = $titleFactory;
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @inheritDoc
	 */
	public function canBeOutOfSync( ?RevisionRecord $revisionToCheckFor = null ): bool {
		// Always latest
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function stabilizeInclusions( array $inclusions, RevisionRecord $mainRevision ): array {
		foreach ( $inclusions as $type => &$inclusionArray ) {
			foreach ( $inclusionArray as &$inclusion ) {
				$latestRev = null;
				if ( $type === 'transclusions' ) {
					if ( $inclusion['source'] === 'local' ) {
						$page = $this->titleFactory->makeTitle( $inclusion['namespace'], $inclusion['title'] );
						$latestRev = $page->exists() ? $this->revisionLookup->getRevisionByTitle( $page ) : null;
					} else {
						$inclusion['revision'] = null;
						$this->hookContainer->run( 'ContentStabilizationFetchForeignRevisionForTransclusion', [
							$inclusion, &$latestRev, null, [ 'inclusionMode' => $this ]
						] );
					}
				} elseif ( $type === 'images' ) {
					$page = $this->titleFactory->makeTitle( NS_FILE, $inclusion['name'] );
					$latestRev = $page->exists() ? $this->revisionLookup->getRevisionByTitle( $page ) : null;
				} else {
					continue;
				}

				if ( !$latestRev ) {
					continue;
				}
				$inclusion['revision'] = $latestRev->getId();
				if ( $type === 'images' ) {
					$file = $this->repoGroup->findFile( $inclusion['name'] );
					if ( !$file ) {
						continue;
					}
					$inclusion['timestamp'] = $file->getTimestamp();
					$inclusion['sha1'] = $file->getSha1();
				}
			}
		}
		return $inclusions;
	}
}
