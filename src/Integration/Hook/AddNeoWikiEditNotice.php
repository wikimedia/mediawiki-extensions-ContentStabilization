<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use BlueSpice\Eclipse\Hook\BlueSpiceEclipseMetadataPanelRenderHook;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;

class AddNeoWikiEditNotice implements BlueSpiceEclipseMetadataPanelRenderHook {

	/**
	 * @param StabilizationLookup $stabilizationLookup
	 */
	public function __construct(
		private readonly StabilizationLookup $stabilizationLookup
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onBlueSpiceEclipseMetadataPanelRender(
		PageSubjects $subjects, bool $canCreate, bool $isRenderingLatest,
		?int $revisionId, PageIdentity $page, Authority $actor, array &$items, array &$notices
	): void {
		if ( !$isRenderingLatest || !$revisionId || !$canCreate ) {
			return;
		}
		if ( !$this->stabilizationLookup->isStabilizationEnabled( $page ) ) {
			return;
		}
		$notices[] = Message::newFromKey( 'contentstabilization-edit-notice-approval-needed' );
	}
}
