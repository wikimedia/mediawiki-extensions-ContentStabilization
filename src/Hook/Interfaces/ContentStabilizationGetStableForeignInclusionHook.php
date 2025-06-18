<?php

namespace MediaWiki\Extension\ContentStabilization\Hook\Interfaces;

interface ContentStabilizationGetStableForeignInclusionHook {

	/**
	 * @param array &$inclusion
	 * @param string $type
	 * @param int $revLimit
	 * @return void
	 */
	public function onContentStabilizationGetStableForeignInclusion(
		array &$inclusion, string $type, int $revLimit
	): void;
}
