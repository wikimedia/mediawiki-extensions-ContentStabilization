<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use ApiMain;
use BSApiMyPageAssignmentStore;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use SpecialPageAssignments;
use TitleFactory;

class AddStabilizationInfoToPageAssignments {

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
		foreach ( $data as $dataSet ) {
			$dataSet->last_stable_date = null;
			$page = $this->titleFactory->newFromID( $dataSet->page_id );
			if ( !$page ) {
				continue;
			}
			$stable = $this->lookup->getLastStablePoint( $page->toPageIdentity() );
			if ( !$stable ) {
				continue;
			}

			$dataSet->last_stable_date = $stable->getRevision()->getTimestamp();
		}
	}

	/**
	 *
	 * @param SpecialPageAssignments $sender
	 * @param array &$deps
	 * @return bool
	 */
	public function onBSPageAssignmentsOverview( $sender, array &$deps ) {
		$deps[] = 'ext.contentStabilization.pageassignments.stabilization';
		return true;
	}
}
