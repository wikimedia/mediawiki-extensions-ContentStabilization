<?php

namespace MediaWiki\Extension\ContentStabilization\Event;

use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use Message;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\TitleEvent;

class StablePointAdded extends TitleEvent {

	/**
	 * @param StablePoint $point
	 */
	public function __construct( StablePoint $point ) {
		parent::__construct( $point->getApprover()->getUser(), $point->getPage() );
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'stable-point-added';
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage( IChannel $forChannel ): Message {
		if ( $this->getAgent()->isSystemUser() ) {
			return Message::newFromKey( 'contentstabilization-stablepoint-added-message-passive' );
		}
		return Message::newFromKey( 'contentstabilization-stablepoint-added-message' );
	}

	/**
	 * @inheritDoc
	 */
	public static function getArgsForTesting(
		UserIdentity $agent, MediaWikiServices $services, array $extra = []
	): array {
		return [];
	}
}
