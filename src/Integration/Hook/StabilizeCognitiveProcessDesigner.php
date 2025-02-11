<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use CognitiveProcessDesigner\Hook\CognitiveProcessDesignerBeforeRenderHook;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use WikiPage;

class StabilizeCognitiveProcessDesigner implements CognitiveProcessDesignerBeforeRenderHook {

	/**
	 * @param StabilizationLookup $stabilizationLookup
	 * @param RevisionLookup $revisionLookup
	 * @param bool $doNotStabilize
	 * @param RequestContext|null $context
	 */
	public function __construct(
		private readonly StabilizationLookup $stabilizationLookup,
		private readonly RevisionLookup $revisionLookup,
		private bool $doNotStabilize = false,
		private ?RequestContext $context = null
	) {
		$this->context = RequestContext::getMain();
	}

	/**
	 * @param PageIdentity|null $forPage
	 * @param WikiPage $diagramPage
	 * @param RevisionRecord|null &$diagramRevision
	 * @return void
	 */
	public function onCognitiveProcessDesignerBeforeRender(
		?PageIdentity $forPage, WikiPage $diagramPage, ?RevisionRecord &$diagramRevision
	) {
		if (
			$this->doNotStabilize ||
			!$forPage ||
			!$forPage->canExist() ||
			!$this->context->getTitle() ||
			!$this->context->getTitle()->canExist() ||
			!$this->context->getTitle()->getId() ||
			$this->context->getTitle()->getId() !== $forPage->getId()
		) {
			return;
		}
		if ( !$this->stabilizationLookup->isStabilizationEnabled( $this->context->getTitle() ) ) {
			return;
		}
		$this->doNotStabilize = true;
		$view = $this->stabilizationLookup->getStableViewFromContext( $this->context );
		$this->doNotStabilize = false;
		if ( !$view ) {
			$diagramRevision = null;
			return;
		}
		$inclusions = $view->getInclusions();
		foreach ( $inclusions['transclusions'] as $transclusion ) {
			if (
				$transclusion['namespace'] === $diagramPage->getNamespace() &&
				$transclusion['title'] === $diagramPage->getDBkey()
			) {
				$diagramRevision = $this->revisionLookup->getRevisionById( $transclusion['revision'] );
			}
		}
	}
}
