<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use MediaWiki\Output\Hook\BeforePageDisplayHook;

class AddStyles implements BeforePageDisplayHook {

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addModuleStyles( [ 'ext.contentStabilization.view.styles' ] );
		$isHistory = $out->getRequest()->getText( 'action', 'view' ) === 'history';
		$title = $out->getTitle();
		if ( $isHistory ||
			( $title && $title->getNamespace() === NS_FILE )
		) {
			$out->addModuleStyles( [ 'ext.contentStabilization.history.styles' ] );
		}
	}
}
