<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use MediaWiki\Extension\ContentStabilization\Integration\OverviewGlobalAction;
use MWStake\MediaWiki\Component\CommonUserInterface\Hook\MWStakeCommonUIRegisterSkinSlotComponents;

class RegisterGlobalAction implements MWStakeCommonUIRegisterSkinSlotComponents {

	/**
	 * @inheritDoc
	 */
	public function onMWStakeCommonUIRegisterSkinSlotComponents( $registry ): void {
		$registry->register(
			'GlobalActionsOverview',
			[
				'special-content-stabilization' => [
					'factory' => static function () {
						return new OverviewGlobalAction();
					}
				]
			]
		);
	}
}
