<?php

namespace MediaWiki\Extension\ContentStabilization\Hook\Interfaces;

use MediaWiki\Extension\ContentStabilization\StablePoint;

interface ContentStabilizationStablePointAddedHook {

	/**
	 * @param StablePoint $stablePoint
	 *
	 * @return void
	 */
	public function onContentStabilizationStablePointAdded( StablePoint $stablePoint ): void;
}
