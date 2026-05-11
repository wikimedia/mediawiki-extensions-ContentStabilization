<?php

namespace Mediawiki\Extension\ContentStabilization\Integration\WikiRAG;

use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\WikiRAG\Hook\WikiRAGMetadataHook;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;

class StabilizationMetadata implements WikiRAGMetadataHook {

	/**
	 * @param StabilizationLookup $stabilizationLookup
	 */
	public function __construct(
		private StabilizationLookup $stabilizationLookup
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onWikiRAGMetadata( PageIdentity $page, RevisionRecord $revision, array &$meta ): void {
		if ( !$this->stabilizationLookup->isStabilizationEnabled( $page ) ) {
			return;
		}
		$stablePoint = $this->stabilizationLookup->getStablePointForRevision( $revision );
		if ( !$stablePoint ) {
			return;
		}
		$meta['stabilizationDate'] = $stablePoint->getTime()->format( 'YmdHis' );
	}
}
