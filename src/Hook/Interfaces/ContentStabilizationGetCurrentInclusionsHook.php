<?php

namespace MediaWiki\Extension\ContentStabilization\Hook\Interfaces;

use MediaWiki\Page\PageIdentity;

interface ContentStabilizationGetCurrentInclusionsHook {

	/**
	 * @param PageIdentity $page
	 * @param array &$res
	 *
	 * @return void
	 */
	public function onContentStabilizationGetCurrentInclusions( PageIdentity $page, array &$res ): void;
}
