<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\PageInfoElement;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\ContentStabilization\StableView;
use MediaWiki\Language\RawMessage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use PageHeader\IPageInfo;

class VersionSwitchPill extends StabilizedPageElement {

	/** @var bool */
	public $hasSwitchToDraft = false;
	/** @var bool */
	public $hasSwitchToStable = false;
	/** @var bool */
	protected $hasImplicitDraft = false;
	/** @var bool */
	protected $canStabilize = false;

	/**
	 * @param IContextSource $context
	 * @param Config $config
	 * @return static
	 */
	public static function factory( IContextSource $context, Config $config ) {
		return new static(
			$context, $config, MediaWikiServices::getInstance()->getService( 'ContentStabilization.Lookup' )
		);
	}

	/**
	 * @return Message
	 */
	public function getLabelMessage() {
		// contentstabilization-pageinfoelement-pill-label-stable
		// contentstabilization-pageinfoelement-pill-label-unstable
		if ( $this->hasSwitchToDraft ) {
			return $this->context->msg( 'contentstabilization-pageinfoelement-pill-label-unstable' );
		} elseif ( $this->hasSwitchToStable ) {
			return $this->context->msg( 'contentstabilization-pageinfoelement-pill-label-stable' );
		}

		return new RawMessage( '' );
	}

	/**
	 * @return Message
	 */
	public function getTooltipMessage() {
		if ( $this->hasSwitchToDraft ) {
			return $this->context->msg(
				'contentstabilization-pageinfoelement-versionswitch-has-unstable-title'
			);
		} elseif ( $this->hasSwitchToStable ) {
			return $this->context->msg(
				'contentstabilization-pageinfoelement-versionswitch-has-stable-title'
			);
		}

		return new RawMessage( '' );
	}

	/**
	 * @return string
	 */
	public function getName() {
		return "bs-frc-version-switch-pill";
	}

	/**
	 * @return string
	 */
	public function getUrl() {
		if ( $this->hasSwitchToDraft ) {
			return $this->context->getTitle()->getFullUrl( 'stable=0' );
		} elseif ( $this->hasSwitchToStable ) {
			return $this->context->getTitle()->getFullUrl( 'stable=1' );
		}

		return '';
	}

	/**
	 * @return string
	 */
	public function getHtmlClass() {
		$state = '';
		if ( $this->hasSwitchToDraft ) {
			$state = 'contentstabilization-pageinfo-page-unstable';
		} elseif ( $this->hasSwitchToStable ) {
			$state = 'contentstabilization-pageinfo-page-stable';
		}

		return $state . ' cs-pageinfo-pill--inactive';
	}

	/**
	 * @return int
	 */
	public function getPosition() {
		return 2;
	}

	/**
	 * @return string Can be one of IPageInfo::TYPE_*
	 */
	public function getType() {
		return IPageInfo::TYPE_PILL;
	}

	/**
	 * @param IContextSource $context
	 * @return bool
	 */
	public function shouldShow( $context ) {
		if ( !parent::shouldShow( $context ) ) {
			return false;
		}

		$view = $this->getStableView();
		if ( !$view ) {
			return false;
		}
		if ( $view->getStatus() === StableView::STATE_IMPLICIT_UNSTABLE ) {
			$this->hasImplicitDraft = true;
		}

		if ( $view->isStable() && $view->doesNeedStabilization() ) {
			$this->hasSwitchToDraft = true;
			$this->canStabilize = MediaWikiServices::getInstance()->getPermissionManager()->userCan(
				'contentstabilization-stabilize',
				$this->context->getUser(),
				$this->context->getTitle()
			);
		} elseif ( !$view->isStable() && $view->hasStable() ) {
			$this->hasSwitchToStable = true;
		} else {
			return false;
		}

		return true;
	}

	/**
	 * @return string Can be one of IPageInfoElement::ITEMCLASS_*
	 */
	public function getItemClass() {
		return IPageInfo::ITEMCLASS_PRO;
	}

	/**
	 * Provides an approve action button on the draft pill when the user can
	 * stabilize the page.
	 *
	 * @return array
	 */
	public function getTypeData(): array {
		if ( $this->hasSwitchToDraft || !$this->canStabilize ) {
			return [];
		}

		$label = $this->hasImplicitDraft
			? $this->context->msg( 'contentstabilization-pageinfoelement-pill-action-update' )->plain()
			: $this->context->msg( 'contentstabilization-pageinfoelement-pill-action-approve' )->plain();

		return [
			'action' => [
				'id'    => 'contentstabilization-stabilize-link',
				'label' => $label,
				'title' => $this->context->msg(
					'contentstabilization-pageinfoelement-versionswitch-has-unstable-title'
				)->plain(),
			],
		];
	}
}
