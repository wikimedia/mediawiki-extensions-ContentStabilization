<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\PreviewLinks;

use MediaWiki\Content\TextContent;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\PreviewLinks\Processor\DefaultPreviewProcessor;
use MediaWiki\Message\Message;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionLookup;

class CSPreviewProcessor extends DefaultPreviewProcessor {

	/**
	 * @param WikiPageFactory $wikiPageFactory
	 * @param StabilizationLookup $stabilizationLookup
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct(
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly StabilizationLookup $stabilizationLookup,
		private readonly RevisionLookup $revisionLookup
		) {
			parent::__construct( $wikiPageFactory );
	}

	/**
	 * @inheritDoc
	 */
	public function applies( $title ): bool {
		if ( $this->stabilizationLookup->isStabilizationEnabled( $title ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getPreviewText( $title, $user, $limit = 350 ): Message {
		$latestRevID = $title->getLatestRevID();
		$revisionToShow = null;
		if ( $this->stabilizationLookup->hasStable( $title ) ) {
			$revisionToShow = $this->stabilizationLookup->getLastStableRevision( $title );
		} else {
			if ( $this->stabilizationLookup->isFirstUnstableAllowed() ) {
				$revisionToShow = $this->revisionLookup->getRevisionById( $latestRevID );
			}
		}
		if ( !$revisionToShow ) {
			return new Message( 'contentstabilization-preview-links-empty-preview-label' );
		}
		$content = $revisionToShow->getContent( 'main' );
		if ( !( $content instanceof TextContent ) ) {
			return new Message( 'contentstabilization-preview-links-empty-preview-label' );
		}
		$text = $content->getText();
		return $this->getPreviewContent( $text, $limit );
	}

}
