<?php

namespace MediaWiki\Extension\ContentStabilization\Hook;

use MediaWiki\Api\ApiQueryRevisions;
use MediaWiki\Api\Hook\APIGetAllowedParamsHook;
use MediaWiki\Api\Hook\APIQueryAfterExecuteHook;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\ILoadBalancer;

class AddStabilizationDataToApiReports implements APIGetAllowedParamsHook, APIQueryAfterExecuteHook {

	/**
	 * @param ILoadBalancer $lb
	 * @param TitleFactory $titleFactory
	 * @param StabilizationLookup $stabilizationLookup
	 */
	public function __construct(
		private readonly ILoadBalancer $lb,
		private readonly TitleFactory $titleFactory,
		private readonly StabilizationLookup $stabilizationLookup
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onAPIGetAllowedParams( $module, &$params, $flags ) {
		if ( !( $module instanceof ApiQueryRevisions ) ) {
			return;
		}
		$params['prop'][ParamValidator::PARAM_TYPE][] = 'stabilization';
	}

	/**
	 * @inheritDoc
	 */
	public function onAPIQueryAfterExecute( $module ) {
		if ( !( $module instanceof ApiQueryRevisions ) ) {
			return;
		}
		$params = $module->extractRequestParams( false );
		if ( !in_array( 'stabilization', $params['prop'] ?? [] ) ) {
			return;
		}
		if ( !in_array( 'ids', $params['prop'] ) ) {
			$module->dieWithError(
				[ 'apierror-invalidparammix-mustusewith', 'rvprop=stabilization', 'rvprop=ids' ], 'missingparam'
			);
		}
		$result = $module->getResult();
		$data = (array)$result->getResultData( [ 'query', 'pages' ], [ 'Strip' => 'all' ] );
		$pageIds = [];
		foreach ( $data as $pageId => $page ) {
			$title = $this->titleFactory->newFromText( $page['title'] );
			if ( !$title || !$this->stabilizationLookup->isStabilizationEnabled( $title ) ) {
				continue;
			}

			if ( is_array( $page ) && array_key_exists( 'revisions', $page ) ) {
				foreach ( $page['revisions'] as $index => $rev ) {
					if ( is_array( $rev ) && array_key_exists( 'revid', $rev ) ) {
						$pageIds[$pageId][$rev['revid']] = $index;
					}
				}
			}
		}
		if ( $pageIds === [] ) {
			return;
		}

		$db = $this->lb->getConnection( DB_REPLICA );

		$query = $db->newSelectQueryBuilder()
			->select( [
				'sp.sp_revision', 'sp.sp_page', 'sp.sp_time', 'sp.sp_user', 'sp.sp_comment', 'u.user_name',
				'spf.sfp_file_timestamp', 'spf.sfp_file_sha1'
			] )
			->from( 'stable_points', 'sp' )
			->join( 'user', 'u', 'sp.sp_user=u.user_id' )
			->leftJoin( 'stable_file_points', 'spf', [
				'spf.sfp_page=sp.sp_page',
				'spf.sfp_revision=sp.sp_revision',
			] );

		$pageConds = [];
		foreach ( $pageIds as $pageId => $revisions ) {
			$pageConds[] = $db->andExpr( [
				'sp_page' => $pageId,
				'sp_revision' => array_keys( $revisions ),
			] );
			$result->addValue(
				[ 'query', 'pages', $pageId ],
				'stabilization_enabled',
				true
			);
		}
		if ( !$pageConds ) {
			return;
		}
		$stablePoints = $query->where( $db->orExpr( $pageConds ) )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $stablePoints as $row ) {
			$index = $pageIds[$row->sp_page][$row->sp_revision] ?? null;
			if ( $index === null ) {
				continue;
			}

			$data = [
				'timestamp' => $row->sp_time,
				'user' => $row->user_name,
				'comment' => $row->sp_comment
			];
			if ( $row->sfp_file_timestamp && $row->sfp_file_sha1 ) {
				$data['file'] = [
					'timestamp' => $row->sfp_file_timestamp,
					'sha1' => $row->sfp_file_sha1
				];
			}
			$result->addValue(
				[ 'query', 'pages', $row->sp_page, 'revisions', $index ],
				'stabilization',
				$data
			);
		}
	}
}
