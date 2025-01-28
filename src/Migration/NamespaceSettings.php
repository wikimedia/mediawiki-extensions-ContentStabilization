<?php

namespace MediaWiki\Extension\ContentStabilization\Migration;

use MediaWiki\Status\Status;
use Wikimedia\Rdbms\ILoadBalancer;

class NamespaceSettings {

	/** @var ILoadBalancer */
	private $loadBalancer;

	/**
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @return Status
	 */
	public function migrate() {
		$s1 = $this->migrateNmSettings();
		$s2 = $this->migrateDynamicConfig();

		return Status::newGood( array_merge( $s1, $s2 ) );
	}

	/**
	 * @return array|bool[]|string[]
	 */
	private function migrateNmSettings(): array {
		if ( !defined( 'BS_LEGACY_CONFIGDIR' ) ) {
			return [];
		}
		if ( !file_exists( BS_LEGACY_CONFIGDIR . '/nm-settings.php' ) ) {
			return [];
		}
		// Replace variables in file
		$settings = $original = file_get_contents( BS_LEGACY_CONFIGDIR . '/nm-settings.php' );
		$settings = str_replace(
			'wgFlaggedRevsNamespaces',
			'wgContentStabilizationEnabledNamespaces',
			$settings
		);
		if ( $settings === $original ) {
			return [ 'migrated_legacy_nm_settings' => 'nothing_to_do' ];
		}
		if ( !file_put_contents( BS_LEGACY_CONFIGDIR . '/nm-settings.php', $settings ) ) {
			return [ 'migrated_legacy_nm_settings' => 'file_write_error' ];
		}
		return [ 'migrated_legacy_nm_settings' => true ];
	}

	/**
	 * @return bool[]|string[]
	 */
	private function migrateDynamicConfig(): array {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		if ( !$db->tableExists( 'mwstake_dynamic_config' ) ) {
			return [ 'migrated_dynamic_config' => 'table_not_found' ];
		}
		$serialized = $db->selectRow(
			'mwstake_dynamic_config',
			[ 'mwdc_serialized' ],
			[
				'mwdc_key' => 'bs-namespacemanager-namespaces',
				'mwdc_is_active' => 1
			],
			__METHOD__
		);
		if ( !$serialized ) {
			return [ 'migrated_dynamic_config' => 'entry_not_found' ];
		}
		$original = $serialized->mwdc_serialized;
		$namespaces = unserialize( $original );
		if ( isset( $namespaces['globals']['wgFlaggedRevsNamespaces' ] ) ) {
			$namespaces['globals']['wgContentStabilizationEnabledNamespaces'] =
				$namespaces['globals']['wgFlaggedRevsNamespaces'];
			unset( $namespaces['globals']['wgFlaggedRevsNamespaces'] );
			$serialized = serialize( $namespaces );
			$res = $db->update(
				'mwstake_dynamic_config',
				[ 'mwdc_serialized' => $serialized ],
				[
					'mwdc_key' => 'bs-namespacemanager-namespaces',
					'mwdc_is_active' => 1
				],
				__METHOD__
			);
			if ( !$res ) {
				return [ 'migrated_dynamic_config' => 'update_failed' ];
			}
		}

		return [ 'migrated_dynamic_config' => true ];
	}

}
