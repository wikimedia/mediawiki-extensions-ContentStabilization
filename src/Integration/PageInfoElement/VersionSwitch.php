<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\PageInfoElement;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\ContentStabilization\StableView;
use MediaWiki\Html\Html;
use MediaWiki\Language\RawMessage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use PageHeader\IPageInfo;

class VersionSwitch extends StabilizedPageElement {

	/** @var bool */
	public $hasSwitchToDraft = false;
	/** @var bool */
	public $hasSwitchToStable = false;
	/** @var bool */
	protected $hasImplicitDraft = false;

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
		if ( $this->hasSwitchToDraft ) {
			return $this->context->msg(
				'contentstabilization-pageinfoelement-versionswitch-has-unstable-text'
			);
		} elseif ( $this->hasSwitchToStable ) {
			return $this->context->msg(
				'contentstabilization-pageinfoelement-versionswitch-has-stable-text'
			);
		}

		return new RawMessage( '' );
	}

	/**
	 *
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
	 *
	 * @return string
	 */
	public function getName() {
		return "bs-frc-version-switch";
	}

	/**
	 *
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
	 *
	 * @return string
	 */
	public function getHtmlClass() {
		if ( $this->hasSwitchToDraft ) {
			return 'contentstabilization-pageinfo-page-unstable';
		} elseif ( $this->hasSwitchToStable ) {
			return 'contentstabilization-pageinfo-page-stable';
		}

		return '';
	}

	/**
	 *
	 * @return int
	 */
	public function getPosition() {
		return 1;
	}

	/**
	 * @return string Can be one of IPageInfo::TYPE_*
	 */
	public function getType() {
		return IPageInfo::TYPE_MENU;
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
		if ( $view->getStatus() === StableView::STATE_IMPLICIT_UNSTABLE ) {
			$this->hasImplicitDraft = true;
		}

		if ( $view->isStable() && $view->doesNeedStabilization() ) {
			$this->hasSwitchToDraft = true;

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
	 *
	 * @return string
	 */
	public function getMenu() {
		// We cannot show diff view if draft is only
		// caused by a change to resources (same rev)
		if ( !$this->hasImplicitDraft ) {
			return $this->makeMenu();
		}

		return '';
	}

	/**
	 *
	 * @return string
	 */
	protected function makeMenu() {
		if ( !$this->hasSwitchToStable ) {
			return '';
		}
		$html = Html::openElement( 'ul' );

		$html .= Html::openElement( 'li' );
		$html .= $this->makeDiffLink();
		$html .= Html::closeElement( 'li' );

		$html .= Html::closeElement( 'ul' );

		return $html;
	}

	/**
	 *
	 * @return string
	 */
	protected function makeDiffLink() {
		$view = $this->getStableView();
		$current = $view->getRevision()->getId();
		$stable = $view->getLastStablePoint() ? $view->getLastStablePoint()->getRevision()->getId() : 0;
		if ( !$stable ) {
			return '';
		}
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		return $linkRenderer->makeLink(
			$this->context->getTitle(),
			$this->context->msg( 'contentstabilization-pageinfoelement-versionswitch-show-diff-label' ),
			[
				'title' => $this->context->msg(
					'contentstabilization-pageinfoelement-versionswitch-show-diff-tooltip'
				)
			],
			[
				'oldid' => $stable,
				'diff' => $current
			]
		);
	}
}
