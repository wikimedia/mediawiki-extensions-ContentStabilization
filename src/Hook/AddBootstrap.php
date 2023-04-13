<?php

namespace MediaWiki\Extension\ContentStabilization\Hook;

use MediaWiki\Hook\BeforePageDisplayHook;

class AddBootstrap implements BeforePageDisplayHook {

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addModules( [ 'ext.contentStabilization.bootstrap' ] );
	}
}
