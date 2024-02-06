<?php

namespace MediaWiki\Extension\ContentStabilization\Hook\Interfaces;

use MediaWiki\Page\PageIdentity;

interface ContentStabilizationIsStabilizationEnabledHook {

	/**
	 * @param PageIdentity $page
	 * @param bool &$result
	 *
	 * @return void
	 */
	public function onContentStabilizationIsStabilizationEnabled( PageIdentity $page, bool &$result ): void;
}
