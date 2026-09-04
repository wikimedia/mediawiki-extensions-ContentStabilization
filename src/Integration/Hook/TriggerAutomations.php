<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use MediaWiki\Extension\ContentStabilization\Hook\Interfaces\ContentStabilizationStablePointAddedHook;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;

class TriggerAutomations implements ContentStabilizationStablePointAddedHook {

	/**
	 * @inheritDoc
	 */
	public function onContentStabilizationStablePointAdded( StablePoint $stablePoint ): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikiAutomations' ) ) {
			return;
		}
		$runner = MediaWikiServices::getInstance()->getService( 'WikiAutomations.Runner' );
		$runner->scheduleTrigger( 'cs-after-approval', [
			$stablePoint->getPage()
		], null, [
			'approver' => $stablePoint->getApprover()->getUser()->getName(),
			'revision' => $stablePoint->getRevision()->getId(),
			'comment' => $stablePoint->getComment()
		] );
	}
}
