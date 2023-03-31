<?php

namespace MediaWiki\Extension\ContentStabilization\Hook\Interfaces;

use MediaWiki\Extension\ContentStabilization\StablePoint;

interface ContentStabilizationStablePointMovedHook {

	/**
	 * @param StablePoint $oldPoint
	 * @param StablePoint $newPoint
	 *
	 * @return void
	 */
	public function onContentStabilizationStablePointMoved( StablePoint $oldPoint, StablePoint $newPoint ): void;
}
