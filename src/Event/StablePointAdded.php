<?php

namespace MediaWiki\Extension\ContentStabilization\Event;

use MediaWiki\Extension\ContentStabilization\Storage\StablePointStore;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\User\UserIdentity;
use Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\EventLink;
use MWStake\MediaWiki\Component\Events\TitleEvent;

class StablePointAdded extends TitleEvent {
	/** @var int */
	protected $newStableRevision;

	/** @var int|null */
	protected $previousStableRevision;

	/**
	 * @param StablePointStore $stablePointStore
	 * @param UserIdentity $approver
	 * @param int $newStableRevisionId
	 * @param PageIdentity $page
	 */
	public function __construct(
		StablePointStore $stablePointStore,
		UserIdentity $approver,
		int $newStableRevisionId,
		PageIdentity $page
	) {
		parent::__construct( $approver, $page );
		$this->newStableRevision = $newStableRevisionId;
		$stableIds = $stablePointStore->getStableRevisionIds( $page );
		$lastStableId = null;
		foreach ( $stableIds as $stableId ) {
			if ( $stableId < $newStableRevisionId ) {
				$lastStableId = $stableId;
			}
		}
		$this->previousStableRevision = $lastStableId;
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'stable-point-added';
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'contentstabilization-stablepoint-added-key-desc' );
	}

	/**
	 * @return string
	 */
	protected function getMessageKey(): string {
		return 'contentstabilization-stablepoint-added-message';
	}

	/**
	 * @inheritDoc
	 */
	public function getLinksIntroMessage( IChannel $forChannel ): ?Message {
		if ( !$this->previousStableRevision ) {
			return null;
		}
		return Message::newFromKey( 'contentstabilization-stablepoint-added-links-intro' );
	}

	/**
	 * @inheritDoc
	 */
	public function getLinks( IChannel $forChannel ): array {
		if ( !$this->previousStableRevision ) {
			return [];
		}
		return [
			new EventLink(
				$this->getTitle()->getFullURL( [
					'diff' => $this->newStableRevision,
					'oldid' => $this->previousStableRevision
				] ),
				Message::newFromKey( 'contentstabilization-stablepoint-added-links-view-changes' )
			)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getArgsForTesting(
		UserIdentity $agent, MediaWikiServices $services, array $extra = []
	): array {
		$title = $extra['title'];
		return [
			$services->getUserFactory()->newFromUserIdentity( $agent ),
			$services->getRevisionLookup()->getFirstRevision( $title )->getId(),
			$title,
			1
		];
	}
}
