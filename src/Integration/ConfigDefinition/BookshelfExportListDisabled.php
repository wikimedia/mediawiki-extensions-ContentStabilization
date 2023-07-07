<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\ConfigDefinition;

use BlueSpice\Bookshelf\ISettingPaths;
use BlueSpice\ConfigDefinition\BooleanSetting;
use BlueSpice\ConfigDefinition\IOverwriteGlobal;
use ExtensionRegistry;

class BookshelfExportListDisabled extends BooleanSetting implements ISettingPaths, IOverwriteGlobal {

	/**
	 *
	 * @return string[]
	 */
	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_BOOK . '/ContentStabilization',
			static::MAIN_PATH_EXTENSION . '/ContentStabilization/' . static::FEATURE_BOOK,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_PRO . '/ContentStabilization',
		];
	}

	/**
	 *
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'contentstabilization-pref-export-list-disabled';
	}

	/**
	 * @return string
	 */
	public function getVariableName() {
		return 'wgBlueSpiceBookshelfExportListDisabled';
	}

	/**
	 * @return bool
	 */
	public function isHidden() {
		return !ExtensionRegistry::getInstance()->isLoaded( 'BlueSpiceBookshelf' );
	}

	/**
	 * @return string
	 */
	public function getGlobalName() {
		return 'wgBlueSpiceBookshelfExportListDisabled';
	}
}
