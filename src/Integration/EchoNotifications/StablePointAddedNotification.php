<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\EchoNotifications;

use MediaWiki\Extension\ContentStabilization\StablePoint;
use MWStake\MediaWiki\Component\Notifications\BaseNotification;

class StablePointAddedNotification extends BaseNotification {

	/**
	 * @param StablePoint $point
	 */
	public function __construct( StablePoint $point ) {
		parent::__construct( 'content-stabilization-stabilized', $point->getApprover(), $point->getPage() );
	}

	/**
	 * @return array|string[]
	 */
	public function getParams() {
		return array_merge( parent::getParams(), [
			'realname' => $this->getUserRealName()
		] );
	}
}
