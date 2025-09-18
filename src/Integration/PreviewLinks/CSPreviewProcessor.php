<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\PreviewLinks;

use MediaWiki\Content\TextContent;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\PreviewLinks\Processor\DefaultPreviewProcessor;
use MediaWiki\Language\RawMessage;
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
		$userCanSeeDrafts = $this->stabilizationLookup->canUserSeeUnstable( $user );
		$latestRevID = $title->getLatestRevID();
		$revisionToShow = null;
		if ( $this->stabilizationLookup->hasStable( $title ) ) {
			$revisionToShow = $this->stabilizationLookup->getLastStableRevision( $title );
		}
		if ( $userCanSeeDrafts ) {
			$revisionToShow = $this->revisionLookup->getRevisionById( $latestRevID );
		}
		if ( !$revisionToShow ) {
			return new RawMessage( '' );
		}
		$content = $revisionToShow->getContent( 'main' );
		if ( !( $content instanceof TextContent ) ) {
			return new RawMessage( '' );
		}
		$text = $content->getText();
		return $this->getPreviewContent( $text, $limit );
	}

}
