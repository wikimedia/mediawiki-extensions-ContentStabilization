<?php

namespace MediaWiki\Extension\ContentStabilization\Data\StabilizedPages;

use DateTime;
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
	 * @param StabilizationLookup $lookup
	 */
	public function __construct( StabilizationLookup $lookup ) {
		$this->lookup = $lookup;
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

			$stableTs = $dataSet->get( Record::LAST_STABLE_TS );
			if ( $stableTs ) {
				$stableTime = DateTime::createFromFormat( 'YmdHis', $stableTs );
				$dataSet->set( Record::LAST_STABLE_TS, $stableTime->format( 'Y/m/d H:i' ) );
			}

			// This is very expensive, maybe even for an SDP, but there is no way to determine implicit draft otherwise
			$currentView = $this->lookup->getStableView( $title->toPageIdentity() );
			if ( !$currentView ) {
				continue;
			}
			$hasChangedInclusions = $state === StableView::STATE_STABLE && $currentView->doesNeedStabilization();
			$dataSet->set( Record::HAS_CHANGED_INCLUSIONS, $hasChangedInclusions );

			$lastStable = $currentView->getLastStablePoint();
			if ( $lastStable ) {
				$approver = $lastStable->getApprover();
				$bot = new StabilizationBot();
				if ( $approver->getUser()->getName() === $bot->getUser()->getName() ) {
					$dataSet->set(
						Record::LAST_APPROVER,
						Message::newFromKey( 'contentstabilization-overview-approver-bot' )->text()
					);
				}
			}
		}

		return $dataSets;
	}
}
