<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use MediaWiki\Output\OutputPage;
use ProfessionalWiki\NeoWiki\EntryPoints\Hook\NeoWikiGetFrontendModulesHook;
use Skin;

class RegisterNeoWikiFrontendModule implements NeoWikiGetFrontendModulesHook {

	/**
	 * @inheritDoc
	 */
	public function onNeoWikiGetFrontendModules( array &$modules, OutputPage $out, Skin $skin ): void {
		$modules[] = 'ext.contentStabilization.neowiki';
	}
}
