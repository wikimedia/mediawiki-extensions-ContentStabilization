<?php

namespace MediaWiki\Extension\ContentStabilization\Integration;

use Message;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\RestrictedTextLink;
use SpecialPage;

class OverviewGlobalAction extends RestrictedTextLink {

	/**
	 *
	 */
	public function __construct() {
		parent::__construct( [
			'role' => 'menuitem'
		] );
	}

	/**
	 *
	 * @return string
	 */
	public function getId(): string {
		return 'ga-ext-content-stabilization';
	}

	/**
	 *
	 * @return string[]
	 */
	public function getPermissions(): array {
		return [ 'contentstabilization-oversight' ];
	}

	/**
	 * @return string
	 */
	public function getHref(): string {
		$tool = SpecialPage::getTitleFor( 'ContentStabilization' );
		return $tool->getLocalURL();
	}

	/**
	 * @return Message
	 */
	public function getText(): Message {
		return Message::newFromKey( 'contentstabilization-global-action-overview' );
	}

	/**
	 * @return Message
	 */
	public function getTitle(): Message {
		return Message::newFromKey( 'contentstabilization-global-action-overview-desc' );
	}

	/**
	 * @return Message
	 */
	public function getAriaLabel(): Message {
		return Message::newFromKey( 'contentstabilization-global-action-overview' );
	}
}
