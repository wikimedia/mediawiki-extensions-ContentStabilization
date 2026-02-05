<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\ConfigDefinition;

use BlueSpice\ConfigDefinition\BooleanSetting;
use BlueSpice\ConfigDefinition\IOverwriteGlobal;
use MediaWiki\Registration\ExtensionRegistry;

class WikiRAGIndexOnlyStable extends BooleanSetting implements IOverwriteGlobal {

	/**
	 *
	 * @return string[]
	 */
	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_QUALITY_ASSURANCE . "/ContentStabilization",
			static::MAIN_PATH_EXTENSION . '/ContentStabilization/' . static::FEATURE_QUALITY_ASSURANCE,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_PRO . "/ContentStabilization",
		];
	}

	/**
	 *
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'contentstabilization-pref-wiki-rag-index-stable-label';
	}

	/**
	 *
	 * @return string
	 */
	public function getHelpMessageKey() {
		return 'contentstabilization-pref-wiki-rag-index-stable-help';
	}

	/**
	 *
	 * @return string
	 */
	public function getGlobalName() {
		return "wgWikiRAGIndexOnlyStable";
	}

	/**
	 * @return bool
	 */
	public function isHidden() {
		return !ExtensionRegistry::getInstance()->isLoaded( 'WikiRAG' );
	}
}
