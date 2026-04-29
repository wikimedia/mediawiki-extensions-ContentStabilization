<?php

namespace MediaWiki\Extension\ContentStabilization\Hook;

use MediaWiki\Extension\ContentStabilization\WikiCron\SyncStabilizedPagesStep;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use MWStake\MediaWiki\Component\WikiCron\WikiCronManager;
use RuntimeException;

class RegisterCrons implements MediaWikiServicesHook {

	/**
	 * @param MediaWikiServices $services
	 * @return void
	 */
	public function onMediaWikiServices( $services ) {
		if ( defined( 'MW_PHPUNIT_TEST' ) || defined( 'MW_QUIBBLE_CI' ) ) {
			return;
		}

		$config = $services->getMainConfig();
		if ( !$config->get( 'ContentStabilizationApprovalSyncEnabled' ) ) {
			return;
		}

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'ContentTransfer' ) ) {
			throw new RuntimeException(
				'ContentStabilization approval sync is enabled ($wgContentStabilizationApprovalSyncEnabled), '
				. 'but ContentTransfer extension is not loaded. '
				. 'ContentTransfer is required as a transport layer for syncing approved pages.'
			);
		}

		/** @var WikiCronManager $cronManager */
		$cronManager = $services->getService( 'MWStake.WikiCronManager' );
		$cronManager->registerCron(
			'contentstabilization-sync-stabilized-pages',
			'0 2 * * *',
			new ManagedProcess(
				[
					'sync-stabilized-pages' => [
						'class' => SyncStabilizedPagesStep::class,
						'services' => [
							'DBLoadBalancer',
							'TitleFactory',
							'ContentTransferTargetManager',
							'ContentTransfer.PagePusherFactory',
							'MainConfig',
							'ContentStabilization.Lookup',
							'ContentTransferPageContentProviderFactory',
						],
					],
				],
				3600
			)
		);
	}
}
