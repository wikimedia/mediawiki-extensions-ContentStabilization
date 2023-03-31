<?php

namespace MediaWiki\Extension\ContentStabilization\Hook;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class RunDatabaseUpdates implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable(
			'stable_points',
			__DIR__ . '/../../db/stable_points.sql'
		);
		$updater->addExtensionTable(
			'stable_file_points',
			__DIR__ . '/../../db/stable_file_points.sql'
		);
		$updater->addExtensionTable(
			'stable_transclusions',
			__DIR__ . '/../../db/stable_transclusions.sql'
		);
		$updater->addExtensionTable(
			'stable_file_transclusions',
			__DIR__ . '/../../db/stable_file_transclusions.sql'
		);

		// Migrate FlaggedRevs data
		$updater->addPostDatabaseUpdateMaintenance(
			'MediaWiki\Extension\ContentStabilization\Migration\MigrateFlaggedRevsData'
		);
	}
}
