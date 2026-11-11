<?php

namespace MediaWiki\Extension\ContentStabilization\Hook;

use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Output\Hook\BeforePageDisplayHook;

class AddBootstrap implements BeforePageDisplayHook {

	/**
	 * @param StabilizationLookup $stabilizationLookup
	 */
	public function __construct(
		private readonly StabilizationLookup $stabilizationLookup
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addModules( [ 'ext.contentStabilization.bootstrap' ] );
		$out->addJsConfigVars(
			'wgContentStabilizationEnabled',
			$out->getTitle() && $this->stabilizationLookup->isStabilizationEnabled( $out->getTitle() )
		);
	}
}
