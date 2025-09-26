<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\ConfigDefinition;

use BlueSpice\ConfigDefinition\BooleanSetting;
use BlueSpice\ConfigDefinition\IOverwriteGlobal;
use MediaWiki\Registration\ExtensionRegistry;

class PDFExportShowTag extends BooleanSetting implements IOverwriteGlobal {

	/**
	 * @return string[]
	 */
	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_EXPORT . "/ContentStabilization",
			static::MAIN_PATH_EXTENSION . '/ContentStabilization/' . static::FEATURE_EXPORT,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_PRO . "/ContentStabilization",
		];
	}

	/**
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'contentstabilization-pref-pdf-show-tag';
	}

	/**
	 * @return string
	 */
	public function getHelpMessageKey() {
		return 'contentstabilization-pref-pdf-show-tag-help';
	}

	/**
	 * @return string
	 */
	public function getGlobalName() {
		return "wgContentStabilizationPDFCreatorShowStabilizationTag";
	}

	/**
	 * @return bool
	 */
	public function isHidden() {
		return !ExtensionRegistry::getInstance()->isLoaded( 'PDFCreator' );
	}
}
