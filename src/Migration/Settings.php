<?php

namespace MediaWiki\Extension\ContentStabilization\Migration;

use Status;
use Wikimedia\Rdbms\ILoadBalancer;

class Settings {

	/** @var array */
	private $mapping = [
		'FlaggedRevsConnectorBookshelfShowNoFR' => 'BlueSpiceBookshelfExportListDisabled',
		'FlaggedRevsConnectorBookshelfShowNoStable' => 'BlueSpiceBookshelfExportListUnstable',
		'FlaggedRevsConnectorBookshelfShowStable' => 'BlueSpiceBookshelfExportListStable',
		'FlaggedRevsConnectorFlaggedRevsHandleIncludes' => 'ContentStabilizationInclusionMode',
		'FlaggedRevsConnectorIndexStableOnly' => 'BlueSpiceExtendedSearchIndexOnlyStable',
		'FlaggedRevsConnectorUEModulePDFShowFRTag' => 'BlueSpiceUEModulePDFShowStabilizationTag',
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
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
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
				if ( $old === 0 || $old === 1 ) {
					// OLD: FR_INCLUDES_NONE, FR_INCLUDES_FREEZE
					$value = '';
				} elseif ( $old === 2 ) {
					// OLD: FR_INCLUDES_STABLE
					$value = 'stable';
				}
			}
			$r = $db->upsert(
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
			if ( !$r ) {
				return Status::newFatal( 'Failed to migrate settings' );
			}
			$newValues[$newVar] = $value;
		}

		// Remove clutter
		$db->delete(
			'bs_settings3', [ 's_name IN (' . $db->makeList( array_keys( $this->mapping ) ) . ')' ], __METHOD__
		);

		return Status::newGood( $newValues );
	}
}
