<?php

namespace MediaWiki\Extension\ContentStabilization\Hook;

use MediaWiki\Preferences\Hook\GetPreferencesHook;

class UserPreference implements GetPreferencesHook {

	/**
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$api = [ 'type' => 'api' ];
		$preferences[ 'history-show-sp_state' ] = $api;
		$preferences[ 'history-show-sp_approver' ] = $api;
		$preferences[ 'history-show-sp_approve_ts' ] = $api;
		$preferences[ 'history-show-sp_approve_comment' ] = $api;
	}
}
