<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use BlueSpice\PageAssignments\Hook\BSPageAssignmentsOverviewHook;
use BSApiMyPageAssignmentStore;
use MediaWiki\Api\ApiMain;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Title\TitleFactory;

class AddStabilizationInfoToPageAssignments implements BSPageAssignmentsOverviewHook {

	/**
	 *
	 * @var StabilizationLookup
	 */
	protected $lookup = null;

	/**
	 *
	 * @var TitleFactory
	 */
	protected $titleFactory = null;

	/**
	 * @param StabilizationLookup $lookup
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( StabilizationLookup $lookup, TitleFactory $titleFactory ) {
		$this->lookup = $lookup;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * Augment store data
	 *
	 * @param ApiMain $apiModule
	 * @param array &$data
	 *
	 * @return bool
	 */
	public function onBSApiExtJSStoreBaseBeforePostProcessData( $apiModule, &$data ) {
		if ( $apiModule instanceof BSApiMyPageAssignmentStore ) {
			$this->extendBSApiMyPageAssignmentStore( $data );
		}
		return true;
	}

	/**
	 * Append "last_stable_date" field to each dataset
	 * @param array &$data
	 * @return void
	 */
	protected function extendBSApiMyPageAssignmentStore( &$data ) {
		$context = RequestContext::getMain();
		$language = $context->getLanguage();
		foreach ( $data as $dataSet ) {
			$dataSet->last_stable_date = null;
			$dataSet->last_stable_date_display = null;
			$page = $this->titleFactory->newFromID( $dataSet->page_id );
			if ( !$page ) {
				continue;
			}
			$stable = $this->lookup->getLastStableRevision( $page->toPageIdentity() );
			if ( !$stable ) {
				continue;
			}

			$timestamp = $stable->getTimestamp();
			if ( !$timestamp ) {
				continue;
			}

			$formattedDate = $language->userDate(
				$timestamp,
				$context->getUser()
			);

			$dataSet->last_stable_date = $timestamp;
			$dataSet->last_stable_date_display = $formattedDate;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onBSPageAssignmentsOverview( array &$deps ): void {
		$deps[] = 'ext.contentStabilization.pageassignments.stabilization';
	}
}
