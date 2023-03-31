<?php

namespace MediaWiki\Extension\ContentStabilization\Event;

use MediaWiki\Extension\ContentStabilization\StablePoint;
use Message;
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
	public function getKey() : string {
		return 'stable-point-added';
	}

	/**
	 * @return Message
	 */
	public function getMessage() : Message {
		if ( $this->getAgent()->isSystemUser() ) {
			return Message::newFromKey( 'contentstabilization-stablepoint-added-message-passive' );
		}
		return Message::newFromKey( 'contentstabilization-stablepoint-added-message' );
	}
}
