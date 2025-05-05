<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\NotifyMe\Hook\NotifyMeBeforeGenerateNotificationHook;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\INotificationEvent;
use MWStake\MediaWiki\Component\Events\ITitleEvent;

class StabilizeNotifications implements NotifyMeBeforeGenerateNotificationHook {

	/**
	 * @var StabilizationLookup
	 */
	private $lookup;

	/**
	 * @param StabilizationLookup $lookup
	 */
	public function __construct( StabilizationLookup $lookup ) {
		$this->lookup = $lookup;
	}

	/**
	 * @inheritDoc
	 */
	public function onNotifyMeBeforeGenerateNotification(
		INotificationEvent $event, UserIdentity $forUser, array $providers, bool &$prevent
	): bool {
		if ( !( $event instanceof ITitleEvent ) ) {
			return true;
		}
		$title = $event->getTitle();
		if ( !$this->lookup->isStabilizationEnabled( $title ) ) {
			return true;
		}
		if ( $this->lookup->canUserSeeUnstable( $forUser ) ) {
			return true;
		}
		$isDraft = !empty( $this->lookup->getPendingUnstableRevisions( $title ) );
		$isFirstDraft = $this->lookup->getLastRawStablePoint( $title ) === null;
		if ( $isFirstDraft && $this->lookup->isFirstUnstableAllowed() ) {
			return true;
		}
		if ( $isDraft ) {
			$prevent = true;
			return false;
		}
		return true;
	}
}
