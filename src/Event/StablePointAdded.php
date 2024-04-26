<?php

namespace MediaWiki\Extension\ContentStabilization\Event;

use DateTime;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Extension\ContentStabilization\Storage\StablePointStore;
use MediaWiki\MediaWikiServices;
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
	 * @param StablePoint $point
	 */
	public function __construct( StablePointStore $stablePointStore, StablePoint $point ) {
		parent::__construct( $point->getApprover()->getUser(), $point->getPage() );
		$this->newStableRevision = $point->getRevision()->getId();
		$stableIds = $stablePointStore->getStableRevisionIds( $point->getPage() );
		$lastStableId = null;
		foreach ( $stableIds as $stableId ) {
			if ( $stableId < $point->getRevision()->getId() ) {
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
			new StablePoint(
				$services->getRevisionLookup()->getFirstRevision( $title ),
				$services->getUserFactory()->newFromUserIdentity( $agent ),
				new DateTime(),
				'Demo approval comment'
			),
			1
		];
	}
}
