<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\PageInfoElement;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\ContentStabilization\StableView;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use PageHeader\IPageInfo;

class PageStatusPill extends StabilizedPageElement {
	/** @var string */
	public $state = 'undefined';
	/** @var bool */
	public $needApproval = false;
	/** @var bool */
	public $canStabilize = false;

	/**
	 *
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
	 *
	 * @return Message
	 */
	public function getLabelMessage() {
		// contentstabilization-pageinfoelement-pill-label-stable
		// contentstabilization-pageinfoelement-pill-label-unstable
		// contentstabilization-pageinfoelement-pill-label-first-unstable
		$state = $this->state === StableView::STATE_IMPLICIT_UNSTABLE ? StableView::STATE_UNSTABLE : $this->state;
		return $this->context->msg(
			'contentstabilization-pageinfoelement-pill-label-' . $state
		);
	}

	/**
	 *
	 * @return string
	 */
	public function getName() {
		return "content-stabilization-page-status-pill";
	}

	/**
	 *
	 * @return Message
	 */
	public function getTooltipMessage() {
		// contentstabilization-pageinfoelement-pagestatus-is-unstable-title
		// contentstabilization-pageinfoelement-pagestatus-is-first-unstable-title
		// contentstabilization-pageinfoelement-pagestatus-is-stable-title
		// contentstabilization-pageinfoelement-pagestatus-is-implicit-unstable-title
		return $this->context->msg(
			'contentstabilization-pageinfoelement-pagestatus-is-' . $this->state . '-title'
		);
	}

	/**
	 *
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
		$this->state = $view->getStatus();
		$this->needApproval = !$view->isStable() && $view->doesNeedStabilization();

		if ( $this->needApproval ) {
			$this->canStabilize = MediaWikiServices::getInstance()->getPermissionManager()->userCan(
				'contentstabilization-stabilize',
				$this->context->getUser(),
				$this->context->getTitle()
			);
		}

		return true;
	}

	/**
	 *
	 * @return int
	 */
	public function getPosition() {
		return 1;
	}

	/**
	 *
	 * @return string
	 */
	public function getItemClass() {
		return IPageInfo::ITEMCLASS_CONTRA;
	}

	/**
	 *
	 * @return string
	 */
	public function getHtmlClass() {
		return 'contentstabilization-pageinfo-page-' . $this->state . ' cs-pageinfo-pill--active';
	}

	/**
	 * @return string Can be one of IPageInfo::TYPE_*
	 */
	public function getType() {
		return IPageInfo::TYPE_PILL;
	}

	/**
	 * Returns a URL to switch to the other view (draft ↔ approved) when
	 * both versions exist, so clicking the pill label toggles the view.
	 *
	 * @return string
	 */
	public function getUrl() {
		$view = $this->getStableView();
		if ( !$view ) {
			return '';
		}
		if ( !$view->isStable() && $view->hasStable() ) {
			return $this->context->getTitle()->getFullUrl( 'stable=1' );
		}
		if ( $view->isStable() && $view->doesNeedStabilization() ) {
			return $this->context->getTitle()->getFullUrl( 'stable=0' );
		}
		return '';
	}

	/**
	 * Provides action button data for the pill renderer when the user can
	 * approve the current draft.
	 *
	 * @return array
	 */
	public function getTypeData(): array {
		if ( !$this->needApproval || !$this->canStabilize ) {
			return [];
		}

		$label = $this->state === StableView::STATE_IMPLICIT_UNSTABLE
			? $this->context->msg( 'contentstabilization-pageinfoelement-pill-action-update' )->plain()
			: $this->context->msg( 'contentstabilization-pageinfoelement-pill-action-approve' )->plain();

		return [
			'action' => [
				'id'    => 'contentstabilization-stabilize-link',
				'label' => $label,
				'title' => $this->context->msg(
					'contentstabilization-pageinfoelement-pagestatus-is-' . $this->state . '-title'
				)->plain(),
			],
		];
	}
}
