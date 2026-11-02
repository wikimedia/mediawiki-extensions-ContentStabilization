<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\NeoWiki;

use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Message\Message;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNotice;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeContext;
use ProfessionalWiki\NeoWiki\Domain\EditNotice\SubjectEditNoticeProvider;

class ApprovalNeededNoticeProvider implements SubjectEditNoticeProvider {

	/**
	 * @param TitleFactory $titleFactory
	 * @param StabilizationLookup $stabilizationLookup
	 */
	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly StabilizationLookup $stabilizationLookup
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getNotices( SubjectEditNoticeContext $context ): array {
		$title = $this->titleFactory->newFromID( $context->pageId->id );
		if ( !$title || !$this->stabilizationLookup->isStabilizationEnabled( $title ) ) {
			return [];
		}

		$message = Message::newFromKey( 'contentstabilization-edit-notice-approval-needed' );
		if ( !$message->exists() || $message->isDisabled() ) {
			return [];
		}

		$html = $message->parseAsBlock();
		if ( trim( $html ) === '' ) {
			return [];
		}

		return [ new SubjectEditNotice(
			key: 'contentstabilization-approval-needed',
			html: $html
		) ];
	}
}
