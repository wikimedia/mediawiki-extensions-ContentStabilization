<?php

namespace MediaWiki\Extension\ContentStabilization\Hook\Interfaces;

use MediaWiki\Extension\ContentStabilization\StablePoint;

interface ContentStabilizationStablePointUpdatedHook {

	/**
	 * @param StablePoint $updatedPoint
	 *
	 * @return void
	 */
	public function onContentStabilizationStablePointUpdated( StablePoint $updatedPoint ): void;
}
