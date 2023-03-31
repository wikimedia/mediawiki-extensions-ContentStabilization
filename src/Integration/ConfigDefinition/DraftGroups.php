<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\ConfigDefinition;

use BlueSpice\ConfigDefinition\ArraySetting;
use BlueSpice\ConfigDefinition\IOverwriteGlobal;

class DraftGroups extends ArraySetting implements IOverwriteGlobal {

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
	 * @return string
	 */
	public function getLabelMessageKey() {
		return 'contentstabilization-pref-draftgroups';
	}

	/**
	 * @return array
	 */
	public function getOptions() {
		$excludeGroups = [
			'bot',
			'autoconfirmed',
			'checkuser',
			'sysop',
			'reviewer'
		];
		$options = [];
		foreach ( $GLOBALS['wgGroupPermissions'] as $group => $permissions ) {
			if ( in_array( $group, $excludeGroups ) ) {
				continue;
			}
			$options[] = $group;
		}
		return $options;
	}

	/**
	 *
	 * @return string
	 */
	public function getGlobalName() {
		return "wgContentStabilizationDraftGroups";
	}

	/**
	 *
	 * @return string
	 */
	public function getHelpMessageKey() {
		return 'contentstabilization-pref-draftgroups-help';
	}

}
