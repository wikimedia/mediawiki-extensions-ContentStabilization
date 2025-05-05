<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Property;

use BlueSpice\SMWConnector\PropertyValueProvider;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\MediaWikiServices;
use SMWDataItem;
use SMWDITime;

class ApprovalDatePropertyValueProvider extends PropertyValueProvider {

	public static function factory() {
		return [ new static(
			MediaWikiServices::getInstance()->getService( 'ContentStabilization.Lookup' )
		) ];
	}

	/**
	 *
	 * @var StabilizationLookup
	 */
	protected $lookup = null;

	/**
	 * @param StabilizationLookup $lookup
	 */
	public function __construct( StabilizationLookup $lookup ) {
		$this->lookup = $lookup;
	}

	/**
	 *
	 * @return string
	 */
	public function getAliasMessageKey() {
		return "contentstabilization-approval-date-sesp-alias";
	}

	/**
	 *
	 * @return string
	 */
	public function getDescriptionMessageKey() {
		return "contentstabilization-approval-date-sesp-desc";
	}

	/**
	 *
	 * @return int
	 */
	public function getType() {
		return SMWDataItem::TYPE_TIME;
	}

	/**
	 *
	 * @return string
	 */
	public function getId() {
		return '_CSAPPROVALDATE';
	}

	/**
	 *
	 * @return string
	 */
	public function getLabel() {
		return "QM/Approval date";
	}

	/**
	 * @param \SESP\AppFactory $appFactory
	 * @param \SMW\DIProperty $property
	 * @param \SMW\SemanticData $semanticData
	 * @return void
	 */
	public function addAnnotation( $appFactory, $property, $semanticData ) {
		$sp = $this->lookup->getLastRawStablePoint( $semanticData->getSubject()->getTitle()->toPageIdentity() );
		if ( $sp instanceof StablePoint ) {
			$semanticData->addPropertyObjectValue(
				$property, SMWDITime::newFromDateTime( $sp->getTime() )
			);
		}
	}
}
