<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StableView;
use MediaWiki\Extension\PageExcerpts\Hook\PageExcerptsChooseRevisionHook;
use MediaWiki\Page\PageIdentity;

class StabilizePageExcerpts implements PageExcerptsChooseRevisionHook {

	/** @var RequestContext */
	private RequestContext $context;

	private ?StableView $view = null;

	/**
	 * @param StabilizationLookup $lookup
	 */
	public function __construct(
		private readonly StabilizationLookup $lookup
	) {
		$this->context = RequestContext::getMain();
	}

	/**
	 * @inheritDoc
	 */
	public function onPageExcerptsChooseRevision(
		PageIdentity $includingPage, PageIdentity $excerptPage, string $excerptName, ?int &$excerptRevisionId
	): void {
		if ( !$this->lookup->isStabilizationEnabled( $includingPage ) ) {
			return;
		}
		$key = $excerptPage->getText() . '#' . $excerptName;
		if ( !$this->view ) {
			$this->view = $this->lookup->getStableViewFromContext( $this->context );
		}
		$transclusions = $this->view ? $this->view->getInclusions() : [ 'transclusions' => [] ];

		foreach ( $transclusions['transclusions'] as $transclusion ) {
			if ( $transclusion['title'] === $key && $transclusion['namespace'] === $excerptPage->getNamespace() ) {
				$excerptRevisionId = $transclusion['revision'];
				return;
			}
		}
	}
}
