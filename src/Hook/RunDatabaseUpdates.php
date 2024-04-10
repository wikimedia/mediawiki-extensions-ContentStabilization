<?php

namespace MediaWiki\Extension\ContentStabilization\Hook;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class RunDatabaseUpdates implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dbType = $updater->getDB()->getType();
		$dir = dirname( __DIR__, 2 );

		$updater->addExtensionTable(
			'stable_points',
			"$dir/db/$dbType/stable_points.sql"
		);
		$updater->addExtensionTable(
			'stable_file_points',
			"$dir/db/$dbType/stable_file_points.sql"
		);
		$updater->addExtensionTable(
			'stable_transclusions',
			"$dir/db/$dbType/stable_transclusions.sql"
		);
		$updater->addExtensionTable(
			'stable_file_transclusions',
			"$dir/db/$dbType/stable_file_transclusions.sql"
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
