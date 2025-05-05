<?php

namespace MediaWiki\Extension\ContentStabilization\Data\StabilizedPages;

use Language;
use MediaWiki\Extension\ContentStabilization\StabilizationBot;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StableView;
use Message;
use MWStake\MediaWiki\Component\DataStore\ISecondaryDataProvider;
use Title;

class SecondaryDataProvider implements ISecondaryDataProvider {

	/**
	 * @var StabilizationLookup
	 */
	private $lookup;

	/**
	 * @var Language
	 */
	private $language;

	/**
	 * @param StabilizationLookup $lookup
	 * @param Language $language
	 */
	public function __construct( StabilizationLookup $lookup, Language $language ) {
		$this->lookup = $lookup;
		$this->language = $language;
	}

	/**
	 * @inheritDoc
	 */
	public function extend( $dataSets ) {
		foreach ( $dataSets as $dataSet ) {
			// TODO: Inject
			$title = Title::makeTitle(
				$dataSet->get( Record::PAGE_NAMESPACE ),
				$dataSet->get( Record::PAGE_TITLE )
			);
			$dataSet->set( Record::PAGE_DISPLAY_TEXT, $title->getPrefixedText() );
			$dataSet->set( Record::PAGE_LINK, $title->getLocalURL() );

			$state = $dataSet->get( Record::STATUS );
			$msg = Message::newFromKey( 'contentstabilization-status-' . $state );
			$dataSet->set( Record::STATUS, $msg->text() );

			// This is very expensive, maybe even for an SDP, but there is no way to determine implicit draft otherwise
			$currentView = $this->lookup->getStableView( $title );
			if ( !$currentView ) {
				continue;
			}
			$hasChangedInclusions = $state === StableView::STATE_STABLE && $currentView->doesNeedStabilization();
			$dataSet->set( Record::HAS_CHANGED_INCLUSIONS, $hasChangedInclusions );

			$lastStable = $this->lookup->getLastRawStablePoint( $title );
			if ( $lastStable ) {
				$approver = $lastStable->getApprover();
				$bot = new StabilizationBot();
				if ( $approver->getUser()->getName() === $bot->getUser()->getName() ) {
					$dataSet->set(
						Record::LAST_APPROVER,
						Message::newFromKey( 'contentstabilization-overview-approver-bot' )->text()
					);
				} else {
					$dataSet->set( Record::LAST_APPROVER, $approver->getUser()->getName() );
				}
				$dataSet->set( Record::LAST_COMMENT, $lastStable->getComment() );
				$dataSet->set(
					Record::LAST_STABLE_TS,
					$this->language->timeanddate( $lastStable->getTime()->format( 'YmdHis' ), true )
				);

			}
		}

		return $dataSets;
	}
}
