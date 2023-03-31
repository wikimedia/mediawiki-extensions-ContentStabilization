<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\ConfigDefinition;

use BlueSpice\ConfigDefinition\ArraySetting;
use BlueSpice\ConfigDefinition\IOverwriteGlobal;
use HTMLFormField;
use HTMLSelectField;

class HandleIncludes extends ArraySetting implements IOverwriteGlobal {

	/**
	 *
	 * @return string[]
	 */
	public function getPaths() {
		return [
			static::MAIN_PATH_FEATURE . '/' . static::FEATURE_QUALITY_ASSURANCE . "/ContentStabilization",
			static::MAIN_PATH_EXTENSION . "/ContentStabilization/" . static::FEATURE_QUALITY_ASSURANCE,
			static::MAIN_PATH_PACKAGE . '/' . static::PACKAGE_PRO . "/ContentStabilization",
		];
	}

	/**
	 *
	 * @return HTMLFormField
	 */
	public function getHtmlFormField() {
		return new HTMLSelectField( $this->makeFormFieldParams() );
	}

	/**
	 *
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'contentstabilization-pref-handleincludes';
	}

	/**
	 *
	 * @return array
	 */
	protected function getOptions() {
		return [
			// NONE - Default behaviour is "freeze"
			$this->msg( 'contentstabilization-pref-handleinclude-none' )->plain() => null,
			$this->msg( 'contentstabilization-pref-handleinclude-stable' )->plain() => 'stable',
		];
	}

	/**
	 *
	 * @return string
	 */
	public function getGlobalName() {
		return "wgContentStabilizationInclusionMode";
	}

	/**
	 *
	 * @return string
	 */
	public function getHelpMessageKey() {
		return 'contentstabilization-pref-handleincludes-help';
	}

}
