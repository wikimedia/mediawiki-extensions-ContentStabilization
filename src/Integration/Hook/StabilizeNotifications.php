<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use Exception;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\NotifyMe\Hook\NotifyMeBeforeGenerateNotificationHook;
use MediaWiki\Page\PageIdentity;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\INotificationEvent;
use MWStake\MediaWiki\Component\Events\ITitleEvent;

class StabilizeNotifications implements NotifyMeBeforeGenerateNotificationHook {

	/**
	 * @var StabilizationLookup
	 */
	private $lookup;

	/** @var array */
	private array $stabilizedPages = [];

	/** @var bool */
	private bool $firstUnstableAllowed;

	/**
	 * @param StabilizationLookup $lookup
	 */
	public function __construct( StabilizationLookup $lookup ) {
		$this->lookup = $lookup;
		$this->firstUnstableAllowed = $lookup->isFirstUnstableAllowed();
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
		try {
			$stabilizationInfo = $this->getStabilizationInfoForPage( $title );
		} catch ( Exception ) {
			$prevent = true;
			return false;
		}

		if ( !$stabilizationInfo ) {
			return true;
		}
		if ( $stabilizationInfo['isFirstDraft'] && $this->firstUnstableAllowed ) {
			return true;
		}
		if ( !$stabilizationInfo['isDraft'] ) {
			return true;
		}
		if ( !$this->lookup->canUserSeeUnstable( $forUser ) ) {
			$prevent = true;
			return false;
		}
		return true;
	}

	/**
	 * @param PageIdentity $page
	 * @return array|null
	 * @throws Exception
	 */
	private function getStabilizationInfoForPage( PageIdentity $page ): ?array {
		if ( isset( $this->stabilizedPages[$page->getId()] ) ) {
			return $this->stabilizedPages[$page->getId()];
		}
		if ( !$this->lookup->isStabilizationEnabled( $page ) ) {
			$this->stabilizedPages[$page->getId()] = null;
			return null;
		}
		$this->stabilizedPages[$page->getId()] = [
			'isDraft' => !empty( $this->lookup->getPendingUnstableRevisions( $page ) ),
			'isFirstDraft' => $this->lookup->getLastRawStablePoint( $page ) === null,
		];
		return $this->stabilizedPages[$page->getId()];
	}
}
