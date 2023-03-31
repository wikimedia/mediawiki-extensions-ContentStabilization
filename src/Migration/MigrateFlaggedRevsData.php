<?php

namespace MediaWiki\Extension\ContentStabilization\Migration;

use LoggedUpdateMaintenance;
use MediaWiki\MediaWikiServices;

require_once dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/maintenance/Maintenance.php';

class MigrateFlaggedRevsData extends LoggedUpdateMaintenance {

	/** @var MediaWikiServices */
	protected $services = null;

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

		// Log
		$log = new Log( $this->services->getDBLoadBalancer() );
		$lStatus = $log->migrate();
		if ( !$lStatus->isOK() ) {
			return false;
		}
		$value['log'] = $lStatus->getValue();

		// Settings
		$log = new Settings( $this->services->getDBLoadBalancer() );
		$lStatus = $log->migrate();
		if ( !$lStatus->isOK() ) {
			return false;
		}
		$value['settings'] = $lStatus->getValue();

		$this->output( json_encode( $value, JSON_PRETTY_PRINT ) );
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
