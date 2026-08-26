<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\PageInfoElement;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\ContentStabilization\StableView;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use PageHeader\IPageInfo;

/**
 * Shows the stabilization state of the page as a pill.
 *
 * If the user can reach both versions of the page, both are rendered as
 * segments of a single combined "switch" pill, the currently shown version
 * being the active segment. Otherwise a single-state pill is rendered.
 */
class PageStatusPill extends StabilizedPageElement {
	/** @var string */
	public $state = 'undefined';
	/** @var bool */
	public $needApproval = false;
	/** @var bool */
	public $canStabilize = false;
	/** @var bool */
	protected $showingStable = false;
	/** @var bool */
	protected $hasDraft = false;
	/** @var bool */
	protected $hasApproved = false;

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
		// contentstabilization-pageinfoelement-pill-label-first-unstable
		$state = $this->state === StableView::STATE_IMPLICIT_UNSTABLE ? StableView::STATE_UNSTABLE : $this->state;
		return $this->context->msg(
			'contentstabilization-pageinfoelement-pill-label-' . $state
		);
	}

	/**
	 * @return string
	 */
	public function getName() {
		return "content-stabilization-page-status-pill";
	}

	/**
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
		$this->showingStable = $view->isStable();
		$this->needApproval = !$this->showingStable && $view->doesNeedStabilization();
		$this->hasDraft = $this->showingStable ? $view->doesNeedStabilization() : true;
		$this->hasApproved = $this->showingStable ? true : $view->hasStable();

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
	 * @return int
	 */
	public function getPosition() {
		return 1;
	}

	/**
	 * @return string
	 */
	public function getItemClass() {
		return IPageInfo::ITEMCLASS_CONTRA;
	}

	/**
	 * @return string
	 */
	public function getHtmlClass() {
		if ( $this->isSwitch() ) {
			return 'contentstabilization-pageinfo-versionswitch';
		}
		return 'contentstabilization-pageinfo-page-' . $this->state . ' cs-pageinfo-pill--active';
	}

	/**
	 * @return string Can be one of IPageInfo::TYPE_*
	 */
	public function getType() {
		return IPageInfo::TYPE_PILL;
	}

	/**
	 * Status pill is intentionally non-interactive in both views.
	 * Navigation between versions is done by the segments of the switch pill.
	 *
	 * @return string
	 */
	public function getUrl() {
		return '';
	}

	/**
	 * Both versions exist and are reachable for the user, so they are rendered
	 * as segments of one combined pill.
	 *
	 * @return bool
	 */
	private function isSwitch(): bool {
		return $this->hasDraft && $this->hasApproved;
	}

	/**
	 * Provides the segments of the combined pill, as well as the action button
	 * data for the pill renderer when the user can approve the current draft.
	 *
	 * @return array
	 */
	public function getTypeData(): array {
		if ( !$this->isSwitch() ) {
			return $this->makeActionData();
		}

		return [
			'segments' => [
				$this->makeApprovedSegment(),
				$this->makeDraftSegment(),
			],
		];
	}

	/**
	 * @return array
	 */
	private function makeApprovedSegment(): array {
		$segment = [
			'label' => $this->context->msg(
				'contentstabilization-pageinfoelement-pill-label-stable'
			)->text(),
			'class' => 'contentstabilization-pageinfo-page-' . StableView::STATE_STABLE,
			'active' => $this->showingStable,
		];
		if ( $this->showingStable ) {
			$segment['title'] = $this->context->msg(
				'contentstabilization-pageinfoelement-pagestatus-is-stable-title'
			)->text();
		} else {
			$segment['title'] = $this->context->msg(
				'contentstabilization-pageinfoelement-versionswitch-has-stable-title'
			)->text();
			$segment['href'] = $this->context->getTitle()->getFullURL( 'stable=1' );
		}

		return $segment;
	}

	/**
	 * @return array
	 */
	private function makeDraftSegment(): array {
		$segment = [
			'label' => $this->context->msg(
				'contentstabilization-pageinfoelement-pill-label-unstable'
			)->text(),
			'class' => 'contentstabilization-pageinfo-page-' .
				( $this->showingStable ? StableView::STATE_UNSTABLE : $this->state ),
			'active' => !$this->showingStable,
		];
		if ( $this->showingStable ) {
			$segment['title'] = $this->context->msg(
				'contentstabilization-pageinfoelement-versionswitch-has-unstable-title'
			)->text();
			$segment['href'] = $this->context->getTitle()->getFullURL( 'stable=0' );
		} else {
			$segment['title'] = $this->getTooltipMessage()->text();
			$segment += $this->makeActionData();
		}

		return $segment;
	}

	/**
	 * @return array
	 */
	private function makeActionData(): array {
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
