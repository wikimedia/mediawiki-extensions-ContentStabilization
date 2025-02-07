<?php

namespace MediaWiki\Extension\ContentStabilization\Migration;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\MediaWikiServices;

require_once dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/maintenance/Maintenance.php';

class MigrateNamespaceSettings extends LoggedUpdateMaintenance {

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$this->output( 'Migrate FlaggedRevs namespace settings... ' );

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();

		$nsSettings = new NamespaceSettings( $lb );
		$nsStatus = $nsSettings->migrate();
		if ( !$nsStatus->isOK() ) {
			$this->output( "failed\n" );
			return false;
		}
		$this->output( "done\n" );
		return true;
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'ContentStabilization::MigrateFlaggedRevsNamespaceSettings';
	}
}

$maintClass = MigrateFlaggedRevsData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
