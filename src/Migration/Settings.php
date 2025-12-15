<?php

namespace MediaWiki\Extension\ContentStabilization\Migration;

use MediaWiki\Status\Status;
use Wikimedia\Rdbms\DBConnRef;
use Wikimedia\Rdbms\ILoadBalancer;

class Settings {

	/** @var array */
	private $mapping = [
		'FlaggedRevsConnectorBookshelfShowNoFR' => 'BlueSpiceBookshelfExportListDisabled',
		'FlaggedRevsConnectorBookshelfShowNoStable' => 'BlueSpiceBookshelfExportListUnstable',
		'FlaggedRevsConnectorBookshelfShowStable' => 'BlueSpiceBookshelfExportListStable',
		'FlaggedRevsConnectorFlaggedRevsHandleIncludes' => 'ContentStabilizationInclusionMode',
		'FlaggedRevsConnectorIndexStableOnly' => 'BlueSpiceExtendedSearchIndexOnlyStable',
		'FlaggedRevsConnectorUEModulePDFShowFRTag' => 'IntegrationPDFCreatorShowStabilizationTag',
		'FlaggedRevsConnectorDraftGroups' => 'ContentStabilizationDraftGroups',
	];

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
		/** @var DBConnRef $db */
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		if ( !$db->tableExists( 'bs_settings3', __METHOD__ ) ) {
			return Status::newGood( [ 'migrated_settings' => 'table_not_found' ] );
		}
		$res = $db->select(
			'bs_settings3',
			[ 's_name', 's_value' ],
			[ 's_name IN (' . $db->makeList( array_keys( $this->mapping ) ) . ')' ],
			__METHOD__
		);

		$newValues = [];
		foreach ( $res as $row ) {
			$newVar = $this->mapping[$row->s_name];
			$value = $row->s_value;
			if ( $row->s_name === 'FlaggedRevsConnectorFlaggedRevsHandleIncludes' ) {
				$old = (int)$value;
				if ( $old === 1 ) {
					// OLD: FR_INCLUDES_FREEZE
					$value = '';
				} elseif ( $old === 0 ) {
					// OLD: FR_INCLUDES_CURRENT
					$value = 'current';
				} elseif ( $old === 2 ) {
					// OLD: FR_INCLUDES_STABLE
					$value = 'stable';
				}
			}
			$db->upsert(
				'bs_settings3',
				[
					's_name' => $newVar,
					's_value' => $value,
				],
				[ 's_name' ],
				[
					's_value' => $value,
				],
				__METHOD__
			);
			$newValues[$newVar] = $value;
		}

		// Remove clutter
		$db->delete(
			'bs_settings3', [ 's_name IN (' . $db->makeList( array_keys( $this->mapping ) ) . ')' ], __METHOD__
		);

		return Status::newGood( $newValues );
	}
}
