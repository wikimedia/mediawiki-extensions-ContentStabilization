<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use MediaWiki\Config\Config;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\WikiRAG\Hook\WikiRAGRunForPageHook;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;

class StabilizeWikiRAG implements WikiRAGRunForPageHook {

	/**
	 * @param StabilizationLookup $lookup
	 * @param Config $config
	 */
	public function __construct(
		private readonly StabilizationLookup $lookup,
		private readonly Config $config
	) {
	}

	public function onWikiRAGRunForPage( PageIdentity $page, ?RevisionRecord &$revision ): void {
		if ( !$this->shouldIndexStableOnly() || !$this->lookup->isStabilizationEnabled( $page ) ) {
			return;
		}
		$stable = $this->lookup->getLastRawStablePoint( $page );
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
	 * @return bool
	 */
	private function shouldIndexStableOnly(): bool {
		return $this->config->get( 'WikiRAGIndexOnlyStable' );
	}
}
