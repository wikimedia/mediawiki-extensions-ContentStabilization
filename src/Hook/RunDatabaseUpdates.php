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

		$updater->addExtensionIndex(
			'stable_points',
			'sp_revision_index',
			"$dir/db/$dbType/stable_points_indices_patch.sql"
		);

		// Migrate FlaggedRevs data
		$updater->addPostDatabaseUpdateMaintenance(
			'MediaWiki\Extension\ContentStabilization\Migration\MigrateFlaggedRevsData'
		);
		$updater->addPostDatabaseUpdateMaintenance(
			'MediaWiki\Extension\ContentStabilization\Migration\MigrateNamespaceSettings'
		);
	}
}
