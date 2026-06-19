<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\NeoWiki;

use MediaWiki\Extension\ContentStabilization\StabilizationBot;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Extension\ContentStabilization\StableView;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Domain\Page\PageDateTime;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderContext;

class StabilizationProperties implements PagePropertyProvider {

	/**
	 * @param TitleFactory $titleFactory
	 * @param StabilizationLookup $stabilizationLookup
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly StabilizationLookup $stabilizationLookup,
		private readonly RevisionLookup $revisionLookup
	) {
	}

	/**
	 * @param PagePropertyProviderContext $context
	 * @return array|mixed[]
	 */
	public function getProperties( PagePropertyProviderContext $context ): array {
		$title = $this->titleFactory->newFromID( $context->pageId->id );
		if ( !$title || !$title->exists() ) {
			return [];
		}
		if ( !$this->stabilizationLookup->isStabilizationEnabled( $title ) ) {
			return [];
		}
		$revision = $this->revisionLookup->getRevisionByTitle( $title );
		if ( !$revision ) {
			return [];
		}
		$point = $this->stabilizationLookup->getStablePointForRevision( $revision );
		if ( $point !== null ) {
			return $this->getFromStablePoint( $point );
		} else {
			return $this->getFromNoData( $title );
		}
	}

	/**
	 * @param StablePoint $point
	 * @return array
	 */
	private function getFromStablePoint( StablePoint $point ): array {
		return [
			// Deprecated - to be removed
			'qm/state' => $this->getStateMessage( StableView::STATE_STABLE ),
			'qm/state_raw' => StableView::STATE_STABLE,
			'qm/approved_by' => $point->getApprover()->getUser()->getName(),
			'qm/approved_at' => $point->getTime()->format( 'YmdHis' ),
			'qm/has_draft' => false,

			'qm_state' => $this->getStateMessage( StableView::STATE_STABLE ),
			'qm_state_raw' => StableView::STATE_STABLE,
			'qm_approved_by' => $point->getApprover()->getUser()->getName(),
			'qm_has_draft' => false,
			'qm_approved_at' => new PageDateTime( $point->getTime()->format( 'YmdHis' ) ),
		];
	}

	/**
	 * @param PageIdentity $page
	 * @return array
	 */
	private function getFromNoData( PageIdentity $page ): array {
		$state = $this->getState( $page );
		return [
			// Deprecated - to be removed
			'qm/state' => $state[1],
			'qm/state_raw' => $state[0],
			'qm/approved_by' => '',
			'qm/approved_at' => '',
			'qm/has_draft' => true,

			'qm_state' => $state[1],
			'qm_state_raw' => $state[0],
			'qm_approved_by' => '',
			'qm_approved_at' => '',
			'qm_has_draft' => true,
		];
	}

	/**
	 * @param PageIdentity $page
	 * @return array
	 */
	private function getState( PageIdentity $page ): array {
		// Use user who can see all versions, to get the latest page state
		$view = $this->stabilizationLookup->getStableView( $page, ( new StabilizationBot() )->getUser(), [
			'forceUnstable' => true
		] );
		if ( $view === null ) {
			return [ '', '' ];
		}
		$state = $view->getStatus();
		if ( $state === StableView::STATE_IMPLICIT_UNSTABLE ) {
			return [ StableView::STATE_UNSTABLE, $this->getStateMessage( StableView::STATE_UNSTABLE ) ];
		}
		return [ $state, $this->getStateMessage( $state ) ];
	}

	/**
	 * @param string $state
	 * @return string
	 */
	private function getStateMessage( string $state ): string {
		$msg = Message::newFromKey( "contentstabilization-status-$state" )->inContentLanguage();
		return $msg->exists() ? $msg->text() : $state;
	}
}
