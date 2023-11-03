<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use MediaWiki\Extension\ContentStabilization\Integration\OverviewGlobalAction;
use MWStake\MediaWiki\Component\CommonUserInterface\Hook\MWStakeCommonUIRegisterSkinSlotComponents;

class RegisterGlobalAction implements MWStakeCommonUIRegisterSkinSlotComponents {

	/**
	 * @inheritDoc
	 */
	public function onMWStakeCommonUIRegisterSkinSlotComponents( $registry ): void {
		$managerEntry = [
			'special-content-stabilization' => [
				'factory' => static function () {
					return new OverviewGlobalAction();
				}
			]
		];

		// BlueSpiceDiscovery 4.4
		$registry->register( 'GlobalActionsOverview', $managerEntry );

		// BlueSpiceDiscovery 4.3 b/c
		$registry->register( 'GlobalActionsManager', $managerEntry );
	}
}
