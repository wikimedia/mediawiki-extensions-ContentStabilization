<?php

namespace MediaWiki\Extension\ContentStabilization\Migration;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\MediaWikiServices;

require_once dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/maintenance/Maintenance.php';

class MigrateFlaggedRevsData extends LoggedUpdateMaintenance {

	/** @var MediaWikiServices */
	protected $services = null;

	/** @var array */
	private $config = [
		'database' => [
			'minQuality' => 1
		]
	];

	public function __construct() {
		parent::__construct();
	}

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$this->output( 'Migrate FlaggedRevs data... ' );

		$this->services = MediaWikiServices::getInstance();
		$value = [];

		// Database migration
		$config = $this->config['database'] ?? [];
		$databaseMigrator = new Database(
			$this->services->getDBLoadBalancer(),
			$config['minQuality'] ?? 1
		);
		$dStatus = $databaseMigrator->migrate();
		if ( !$dStatus->isOK() ) {
			return false;
		}
		$value['database'] = $dStatus->getValue();
		$this->output( "Migrated database\n" );

		// Log comments - FR stores approval comments in log only
		$logComments = new LogComments( $this->services->getDBLoadBalancer() );
		$lcStatus = $logComments->migrate();
		if ( !$lcStatus->isOK() ) {
			return false;
		}
		$value['comments'] = $lcStatus->getValue();
		$this->output( "Migrated approval comments\n" );

		// Log
		$log = new Log( $this->services->getDBLoadBalancer() );
		$lStatus = $log->migrate();
		if ( !$lStatus->isOK() ) {
			return false;
		}
		$value['log'] = $lStatus->getValue();
		$this->output( "Migrated logs\n" );

		// Settings
		$settings = new Settings( $this->services->getDBLoadBalancer() );
		$lStatus = $settings->migrate();
		if ( !$lStatus->isOK() ) {
			return false;
		}
		$value['settings'] = $lStatus->getValue();
		$this->output( "Migrated settings\n" );

		$this->output( json_encode( $value, JSON_PRETTY_PRINT ) . "\n" );
		return true;
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'ContentStabilization::MigrateFlaggedRevsData';
	}
}

$maintClass = MigrateFlaggedRevsData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
