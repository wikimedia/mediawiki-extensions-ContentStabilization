<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use MediaWiki\Api\ApiQueryRevisions;
use MediaWiki\Api\Hook\ApiQueryBaseBeforeQueryHook;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Hook\ModifyExportQueryHook;

class OverrideApis implements ModifyExportQueryHook, ApiQueryBaseBeforeQueryHook {

	/**
	 * @param StabilizationLookup $stabilizationLookup
	 */
	public function __construct( private readonly StabilizationLookup $stabilizationLookup ) {
	}

	/**
	 * @inheritDoc
	 */
	public function onApiQueryBaseBeforeQuery(
		$module, &$tables, &$fields, &$conds, &$query_options, &$join_conds, &$hookData
	) {
		if ( !( $module instanceof ApiQueryRevisions ) ) {
			return;
		}
		if ( $module->getRequest()->getText( 'rvprop' ) !== 'content' ) {
			return;
		}
		$user = $module->getUser();
		if ( $this->stabilizationLookup->canUserSeeUnstable( $user ) ) {
			return;
		}
		$tables[] = 'stable_points';
		$join_conds['stable_points'] = [
			'JOIN',
			'sp_revision=rev_id'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function onModifyExportQuery( $db, &$tables, $cond, &$opts, &$join_conds, &$conds ) {
		$user = RequestContext::getMain()->getUser();
		if ( $this->stabilizationLookup->canUserSeeUnstable( $user ) ) {
			return;
		}
		$tables[] = 'stable_points';
		$join_conds['stable_points'] = [
			'JOIN',
			'sp_revision=rev_id'
		];
	}
}
