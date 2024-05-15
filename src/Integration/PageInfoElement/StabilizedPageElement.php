<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\PageInfoElement;

use Config;
use IContextSource;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StableView;
use PageHeader\PageInfo;

abstract class StabilizedPageElement extends PageInfo {
	/** @var StableView|null */
	private $view = false;
	/** @var StabilizationLookup */
	protected $lookup;

	/**
	 *
	 * @param IContextSource $context
	 * @param Config $config
	 * @param StabilizationLookup $lookup
	 */
	public function __construct( IContextSource $context, Config $config, StabilizationLookup $lookup ) {
		parent::__construct( $context, $config );
		$this->lookup = $lookup;
	}

	/**
	 *
	 * @param IContextSource $context
	 * @return bool
	 */
	public function shouldShow( $context ) {
		if ( $this->context->getRequest()->getText( 'action', 'view' ) !== 'view' ) {
			return false;
		}
		$title = $context->getTitle();
		if ( !$title ) {
			return false;
		}
		if ( !$this->lookup->isStabilizationEnabled( $title->toPageIdentity() ) ) {
			return false;
		}
		if ( !$this->lookup->canUserSeeUnstable( $context->getUser() ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @return StableView|null
	 */
	protected function getStableView(): ?StableView {
		if ( $this->view === false ) {
			$options = [];
			$oldId = $this->context->getRequest()->getInt( 'oldid' );
			if ( $oldId > 0 ) {
				$options['upToRevision'] = $oldId;
			}
			$explicitlyStable = $this->lookup->getStableParamFromRequest( $this->context->getRequest() );
			if ( $explicitlyStable !== null ) {
				$options['forceUnstable'] = !$explicitlyStable;
			}
			if ( !$this->context->getTitle() || !$this->context->getUser() ) {
				$this->view = null;
				return $this->view;
			}

			$this->view = $this->lookup->getStableView(
				$this->context->getTitle()->toPageIdentity(), $this->context->getUser(), $options
			);
		}
		return $this->view;
	}
}
