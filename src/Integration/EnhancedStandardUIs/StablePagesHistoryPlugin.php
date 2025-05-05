<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\EnhancedStandardUIs;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\EnhancedStandardUIs\IHistoryPlugin;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;

class StablePagesHistoryPlugin implements IHistoryPlugin {

	/** @var StabilizationLookup */
	private $lookup;

	/** @var Language */
	private $language;

	/** @var UserFactory */
	private $userFactory;

	/**
	 *
	 * @param StabilizationLookup $lookup
	 */
	public function __construct( StabilizationLookup $lookup, Language $language, UserFactory $userFactory ) {
		$this->lookup = $lookup;
		$this->language = $language;
		$this->userFactory = $userFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModules( $historyAction ): array {
		if ( !$this->lookup->isStabilizationEnabled( $historyAction->getTitle()->toPageIdentity() ) ) {
			return [];
		}
		return [
			'ext.contentStabilization.history.styles',
			'ext.contentStabilization.enhanced.history'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function ammendRow( $historyAction, &$entry, &$attribs, &$classes ) {
		if ( !$this->lookup->isStabilizationEnabled( $historyAction->getTitle()->toPageIdentity() ) ) {
			return;
		}

		try {
			$point = $this->lookup->getStablePointForRevisionId( (int)$entry[ 'id' ] );
		} catch ( \Throwable $e ) {
			// Cannot find info on stable file point - sanity
			$point = null;
		}
		$user = RequestContext::getMain()->getUser();
		if ( !$point ) {
			$entry['sp_approved'] = false;
			$classes[] = 'content-stabilization-not-stable';
			$title = $historyAction->getTitle();
			if ( !$title ) {
				// Something wrong
				return;
			}

			if ( !$this->lookup->canUserSeeUnstable( $user ) && !$this->showFirstUnstable( $title ) ) {
				$classes[] = 'content-stabilization-hidden';
			} else {
				$lastStable = $this->lookup->getLastRawStablePoint( $title->toPageIdentity() );
				$entry['sp_approver'] = '';
				$entry['sp_approve_ts'] = '';
				$entry['sp_approve_comment'] = '';
				if ( !$lastStable ) {
					$entry['sp_state'] = Message::newFromKey( 'contentstabilization-status-first-unstable' )->text();
					return;
				} else {
					$entry['sp_state'] = Message::newFromKey( 'contentstabilization-status-unstable' )->text();
				}
			}
			return;
		}
		$classes[] = 'content-stabilization-stable';
		$actor = $point->getApprover();
		$user = $this->userFactory->newFromAuthority( $actor );
		$actorName = $user->getName();

		$timestamp = $this->language->userTimeAndDate( $point->getTime()->format( 'YmdHis' ), $user );
		$comment = $point->getComment();
		if ( !$comment ) {
			$comment = '-';
		}

		$entry['sp_approved'] = true;
		$entry['sp_state'] = Message::newFromKey( 'contentstabilization-status-stable' )->parse();
		$entry['sp_approver'] = $actorName;
		$entry['sp_approve_ts'] = $timestamp;
		$entry['sp_approve_comment'] = $comment;
		$classes[] = 'content-stabilization-stable-data';
	}

	/**
	 * @param Title $title
	 *
	 * @return bool
	 */
	private function showFirstUnstable( Title $title ): bool {
		$isFirstUnstable = !$this->lookup->hasStable( $title );
		return $isFirstUnstable && $this->lookup->isFirstUnstableAllowed();
	}

}
