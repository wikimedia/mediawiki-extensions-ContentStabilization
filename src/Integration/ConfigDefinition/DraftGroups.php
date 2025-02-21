<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\ConfigDefinition;

use BlueSpice\ConfigDefinition;
use BlueSpice\ConfigDefinition\ArraySetting;
use BlueSpice\ConfigDefinition\IOverwriteGlobal;
use BlueSpice\Utility\GroupHelper;
use Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\MediaWikiServices;

class DraftGroups extends ArraySetting implements IOverwriteGlobal {

	/**
	 * @param IContextSource $context
	 * @param Config $config
	 * @param string $name
	 * @return ConfigDefinition|static
	 */
	public static function getInstance( $context, $config, $name ) {
		$groupHelper = MediaWikiServices::getInstance()->getService( 'BSUtilityFactory' )->getGroupHelper();
		return new static( $context, $config, $name, $groupHelper );
	}

	/**
	 * @param IContextSource $context
	 * @param Config $config
	 * @param string $name
	 * @param GroupHelper $groupHelper
	 */
	public function __construct( $context, $config, $name, private readonly GroupHelper $groupHelper ) {
		parent::__construct( $context, $config, $name );
	}

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
		return $this->groupHelper->getGroupsForDisplay( [
			'blacklist' => [ 'sysop', 'reviewer' ]
		] );
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
