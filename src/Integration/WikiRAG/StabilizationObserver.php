<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\WikiRAG;

use MediaWiki\Extension\ContentStabilization\Hook\Interfaces\ContentStabilizationStablePointAddedHook;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Extension\WikiRAG\ChangeObserver\HookObserver;
use MediaWiki\Extension\WikiRAG\Factory;
use MediaWiki\HookContainer\HookContainer;

class StabilizationObserver extends HookObserver implements ContentStabilizationStablePointAddedHook {

	/**
	 * @param HookContainer $hookContainer
	 * @param Factory $ragFactory
	 */
	public function __construct( HookContainer $hookContainer, private readonly Factory $ragFactory ) {
		parent::__construct( $hookContainer );
		$this->hookContainer->register(
			'ContentStabilizationStablePointAdded',
			[ $this, 'onContentStabilizationStablePointAdded' ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onContentStabilizationStablePointAdded( StablePoint $stablePoint ): void {
		$this->scheduler?->schedule(
			$stablePoint->getPage(),
			// Export full pipeline
			$this->ragFactory->getPipeline()
		);
	}
}
