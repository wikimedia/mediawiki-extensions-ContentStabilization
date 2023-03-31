<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Property;

use BlueSpice\SMWConnector\PropertyValueProvider;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\MediaWikiServices;
use SMWDataItem;
use SMWDINumber;

class DocumentVersionPropertyValueProvider extends PropertyValueProvider {

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
		return "contentstabilization-document-version-sesp-alias";
	}

	/**
	 *
	 * @return string
	 */
	public function getDescriptionMessageKey() {
		return "contentstabilization-document-version-sesp-desc";
	}

	/**
	 *
	 * @return string
	 */
	public function getId() {
		return '_CSDOCVERSION';
	}

	/**
	 *
	 * @return string
	 */
	public function getLabel() {
		return "QM/Document version";
	}

	/**
	 *
	 * @return int
	 */
	public function getType() {
		return SMWDataItem::TYPE_NUMBER;
	}

	/**
	 * @param \SESP\AppFactory $appFactory
	 * @param \SMW\DIProperty $property
	 * @param \SMW\SemanticData $semanticData
	 * @return void
	 */
	public function addAnnotation( $appFactory, $property, $semanticData ) {
		$title = $semanticData->getSubject()->getTitle();
		if ( !$this->lookup->isStabilizationEnabled( $title->toPageIdentity() ) ) {
			return;
		}
		$points = $this->lookup->getStablePointsForPage( $title->toPageIdentity() );
		$semanticData->addPropertyObjectValue(
			$property, new SMWDINumber( count( $points ) )
		);
	}
}
