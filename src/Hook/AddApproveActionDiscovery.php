<?php

namespace MediaWiki\Extension\ContentStabilization\Hook;

use BlueSpice\Discovery\Hook\BlueSpiceDiscoveryTemplateDataProviderAfterInit;

class AddApproveActionDiscovery implements BlueSpiceDiscoveryTemplateDataProviderAfterInit {

	/**
	 * @inheritDoc
	 */
	public function onBlueSpiceDiscoveryTemplateDataProviderAfterInit( $registry ): void {
		$registry->register( 'actions_secondary', 'ca-cs-approve' );
		$registry->unregister( 'toolbox', 'ca-cs-approve' );
	}
}
