<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Property;

use BlueSpice\SMWConnector\PropertyValueProvider;
use MediaWiki\Extension\ContentStabilization\StabilizationBot;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\MediaWikiServices;
use Message;
use SMWDataItem;
use SMWDIBlob;

class DocumentStatePropertyValueProvider extends PropertyValueProvider {

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
		return "contentstabilization-document-state-sesp-alias";
	}

	/**
	 *
	 * @return string
	 */
	public function getDescriptionMessageKey() {
		return "contentstabilization-document-state-sesp-desc";
	}

	/**
	 *
	 * @return int
	 */
	public function getType() {
		return SMWDataItem::TYPE_BLOB;
	}

	/**
	 *
	 * @return string
	 */
	public function getId() {
		return '_CSDOCSTATE';
	}

	/**
	 *
	 * @return string
	 */
	public function getLabel() {
		return "QM/Document state";
	}

	/**
	 * @param \SESP\AppFactory $appFactory
	 * @param \SMW\DIProperty $property
	 * @param \SMW\SemanticData $semanticData
	 * @return void
	 */
	public function addAnnotation( $appFactory, $property, $semanticData ) {
		$title = $semanticData->getSubject()->getTitle();
		if ( $title === null ) {
			return;
		}
		if ( !$this->lookup->isStabilizationEnabled( $title->toPageIdentity() ) ) {
			return;
		}
		// Use user who can see all versions, to get the latest page state
		$view = $this->lookup->getStableView( $title->toPageIdentity(), ( new StabilizationBot() )->getUser(), [
			'forceUnstable' => true
		] );
		if ( $view === null ) {
			return;
		}
		$state = $view->getStatus();
		$msg = Message::newFromKey( "contentstabilization-status-$state" );
		if ( !$msg->exists() ) {
			return;
		}
		$semanticData->addPropertyObjectValue(
			$property,
			new SMWDIBlob( $msg->text() )
		);
	}
}
