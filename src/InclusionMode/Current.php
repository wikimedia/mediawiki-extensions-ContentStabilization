<?php

namespace MediaWiki\Extension\ContentStabilization\InclusionMode;

use MediaWiki\Extension\ContentStabilization\InclusionMode;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use RepoGroup;
use TitleFactory;

class Current implements InclusionMode {
	/** @var RevisionLookup */
	private $revisionLookup;
	/** @var RepoGroup */
	private $repoGroup;
	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @param RevisionLookup $revisionLookup
	 * @param RepoGroup $repoGroup
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		RevisionLookup $revisionLookup, RepoGroup $repoGroup, TitleFactory $titleFactory
	) {
		$this->revisionLookup = $revisionLookup;
		$this->repoGroup = $repoGroup;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @return bool
	 */
	public function canBeOutOfSync(): bool {
		// Always latest
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function stabilizeInclusions( array $inclusions, RevisionRecord $mainRevision ): array {
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
				$latestRev = $this->revisionLookup->getRevisionByTitle( $page );
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
