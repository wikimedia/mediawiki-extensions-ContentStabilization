<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\ConfigDefinition;

use BlueSpice\ConfigDefinition\BooleanSetting;
use BlueSpice\ConfigDefinition\IOverwriteGlobal;
use ExtensionRegistry;

class SearchIndexOnlyStable extends BooleanSetting implements IOverwriteGlobal {

	/**
	 *
	 * @return string[]
	 */
	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_SEARCH . "/ContentStabilization",
			static::MAIN_PATH_EXTENSION . '/ContentStabilization/' . static::FEATURE_SEARCH,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_PRO . "/ContentStabilization",
		];
	}

	/**
	 *
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'contentstabilization-pref-index-stable';
	}

	/**
	 *
	 * @return string
	 */
	public function getGlobalName() {
		return "wgBlueSpiceExtendedSearchIndexOnlyStable";
	}

	/**
	 * @return bool
	 */
	public function isHidden() {
		return !ExtensionRegistry::getInstance()->isLoaded( 'BlueSpiceExtendedSearch' );
	}
}
