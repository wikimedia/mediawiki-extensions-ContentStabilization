<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\PageInfoElement;

use Html;
use IContextSource;
use MediaWiki\Config\Config;
use MediaWiki\Extension\ContentStabilization\StableView;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use PageHeader\IPageInfo;

class PageStatusDropdown extends StabilizedPageElement {
	/** @var string */
	public $state = 'undefined';
	/** @var bool */
	public $needApproval = false;

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
		// contentstabilization-pageinfoelement-pagestatus-is-unstable-text
		// contentstabilization-pageinfoelement-pagestatus-is-first-unstable-text
		// contentstabilization-pageinfoelement-pagestatus-is-stable-text
		$state = $this->state === StableView::STATE_IMPLICIT_UNSTABLE ? StableView::STATE_UNSTABLE : $this->state;
		return $this->context->msg(
			'contentstabilization-pageinfoelement-pagestatus-is-' . $state . '-text'
		);
	}

	/**
	 *
	 * @return string
	 */
	public function getName() {
		return "content-stabilization-page-status";
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

		return true;
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
		return 'contentstabilization-pageinfo-page-' . $this->state;
	}

	/**
	 *
	 * @return string
	 */
	public function getType() {
		if ( $this->needApproval ) {
			return IPageInfo::TYPE_MENU;
		} else {
			return IPageInfo::TYPE_TEXT;
		}
	}

	/**
	 *
	 * @return string
	 */
	public function getMenu() {
		if ( $this->getType() === IPageInfo::TYPE_TEXT ) {
			return '';
		}

		if ( !$this->needApproval ) {
			return '';
		}

		$canStabilize = MediaWikiServices::getInstance()->getPermissionManager()->userCan(
			'contentstabilization-stabilize',
			$this->context->getUser(),
			$this->context->getTitle()
		);
		if ( !$canStabilize ) {
			return '';
		}
		return $this->makeMenu();
	}

	/**
	 *
	 * @return string
	 */
	public function makeMenu() {
		$html = Html::openElement( 'ul', [
			'class' => 'content-stabilization-stabilize-actions'
		] );
		$html .= Html::openElement( 'li' );
		$html .= $this->makeStabilizationLink();
		$html .= Html::closeElement( 'li' );

		$html .= Html::closeElement( 'ul' );

		return $html;
	}

	/**
	 *
	 * @return string
	 */
	protected function makeStabilizationLink() {
		$html = Html::openElement( 'a', [
			'href' => '#',
			'class' => 'dropdown-item',
			'id' => 'contentstabilization-stabilize-link'
		] );

		$html .= Html::element(
			'span',
			[
				'class' => 'contentstabilization-stabilize'
			],
			$this->state === StableView::STATE_IMPLICIT_UNSTABLE
				? $this->context->msg( 'contentstabilization-pageinfoelement-pagestatus-update' )->plain()
				: $this->context->msg( 'contentstabilization-pageinfoelement-pagestatus-accept' )->plain()
		);

		$html .= Html::closeElement( 'a' );

		return $html;
	}
}
