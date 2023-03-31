<?php

namespace MediaWiki\Extension\ContentStabilization\Hook\Interfaces;

use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Permissions\Authority;

interface ContentStabilizationStablePointRemovedHook {

	/**
	 * @param StablePoint $removedPoint
	 * @param Authority $remover
	 *
	 * @return void
	 */
	public function onContentStabilizationStablePointRemoved(
		StablePoint $removedPoint, Authority $remover
	): void;
}
