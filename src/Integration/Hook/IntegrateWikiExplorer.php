<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use DateTime;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * //phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
 */
class IntegrateWikiExplorer {

	/**
	 * Executed on ExtensionFunctions, needed due to how bad WikiExplorer is
	 * @return void
	 */
	public static function register() {
		$handler = new static(
			MediaWikiServices::getInstance()->getService( 'ContentStabilization.Lookup' ),
			MediaWikiServices::getInstance()->getDBLoadBalancer()
		);

		$GLOBALS['wgHooks']['WikiExplorer::queryPagesWithFilter'][] =
			[ $handler, 'onWikiExplorer__queryPagesWithFilter' ];
		$GLOBALS['wgHooks']['WikiExplorer::buildDataSets'][] =
			[ $handler, 'onWikiExplorer__buildDataSets' ];
	}

	/**
	 * @var StabilizationLookup
	 */
	private $lookup;

	/**
	 * @var ILoadBalancer
	 */
	private $lb;

	/**
	 * @param StabilizationLookup $lookup
	 * @param ILoadBalancer $lb
	 */
	public function __construct( StabilizationLookup $lookup, ILoadBalancer $lb ) {
		$this->lookup = $lookup;
		$this->lb = $lb;
	}

	/**
	 * @param array $filters
	 * @param array &$tables
	 * @param array &$fields
	 * @param array &$conditions
	 * @param array &$joinOptions
	 */
	public function onWikiExplorer__queryPagesWithFilter( $filters, &$tables, &$fields, &$conditions, &$joinOptions ) {
		$tables[] = 'stable_points';
		$joinOptions["stable_points"] = [
			'LEFT OUTER JOIN', "page_id=sp_page",
		];
		$fields[] = "NOT( ISNULL( MAX( sp_revision ) ) ) as contentstabilization_state";
		$fields[] = "MAX( sp_time ) as contentstabilization_date";
		$fields[] = "MAX( sp_revision ) <> page_latest as contentstabilization_is_new_available";

		if ( array_key_exists( 'flaggedrevs_state', $filters ) ) {
			if ( !$filters['contentstabilization_state'][0]['value'] ) {
				$conditions[] = "sp_revision IS NULL";
			} else {
				$conditions[] = "sp_revision IS NOT NULL";
			}
		}
		if ( array_key_exists( 'contentstabilization_date', $filters ) ) {
			$date = DateTime::createFromFormat( 'm-d-Y', $filters['contentstabilization_date'][0]['value'] );
			if ( $date ) {
				switch ( $filters['contentstabilization_date'][0]['operator'] ) {
					case 'lt':
						$conditions[] = "sp_time < " .
							$this->lb->getConnection( DB_REPLICA )->addQuotes( $date->format( 'YmdHis' ) );
						break;
					case 'gt':
						$conditions[] = "sp_time > " .
							$this->lb->getConnection( DB_REPLICA )->addQuotes( $date->format( 'YmdHis' ) );
						break;
					case 'eq':
						$conditions[] = "sp_time = " .
							$this->lb->getConnection( DB_REPLICA )->addQuotes( $date->format( 'YmdHis' ) );
						break;

				}
			}
		}
	}

	/**
	 * @param array &$data
	 *
	 * @return void
	 */
	public function onWikiExplorer__buildDataSets( &$data ) {
		if ( empty( $data ) ) {
			return;
		}
		foreach ( $data as &$row ) {
			$row['contentstabilization_state'] = $row['contentstabilization_state'] === '1';
			$row['contentstabilization_is_new_available'] = $row['contentstabilization_is_new_available'] === '1';
			$row['is_contentstabilization_enabled'] = $this->lookup->isStabilizedNamespace(
				(int)$row['page_namespace']
			);
		}
	}
}
