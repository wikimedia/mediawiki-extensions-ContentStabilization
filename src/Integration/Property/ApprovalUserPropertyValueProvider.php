<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Property;

use BlueSpice\SMWConnector\PropertyValueProvider;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\TitleFactory;
use SMW\DIWikiPage;

class ApprovalUserPropertyValueProvider extends PropertyValueProvider {

	public static function factory() {
		return [ new static(
			MediaWikiServices::getInstance()->getService( 'ContentStabilization.Lookup' ),
			MediaWikiServices::getInstance()->getTitleFactory()
		) ];
	}

	/**
	 * @var StabilizationLookup
	 */
	protected $lookup = null;

	/**
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
	 * @return string
	 */
	public function getAliasMessageKey() {
		return "contentstabilization-approval-user-sesp-alias";
	}

	/**
	 * @return string
	 */
	public function getDescriptionMessageKey() {
		return "contentstabilization-approval-user-sesp-desc";
	}

	/**
	 * @return string
	 */
	public function getId() {
		return '_CSAPPROVALUSER';
	}

	/**
	 * @return string
	 */
	public function getLabel() {
		return "QM/Approval by";
	}

	/**
	 * @param \SESP\AppFactory $appFactory
	 * @param \SMW\DIProperty $property
	 * @param \SMW\SemanticData $semanticData
	 */
	public function addAnnotation( $appFactory, $property, $semanticData ) {
		$sp = $this->lookup->getLastRawStablePoint( $semanticData->getSubject()->getTitle()->toPageIdentity() );
		if ( $sp instanceof StablePoint ) {
			$title = $this->titleFactory->makeTitle( NS_USER, $sp->getApprover()->getUser()->getName() );
			$semanticData->addPropertyObjectValue( $property, DIWikiPage::newFromTitle( $title ) );
		}
	}
}
