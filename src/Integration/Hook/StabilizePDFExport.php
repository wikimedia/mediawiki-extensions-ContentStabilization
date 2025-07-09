<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use BlueSpice\UEModulePDF\Hook\BSUEModulePDFBeforeAddingStyleBlocksHook;
use BlueSpice\UEModulePDF\Hook\BSUEModulePDFbeforeGetPageHook;
use BlueSpice\UEModulePDF\Hook\BSUEModulePDFgetPageHook;
use Config;
use DOMElement;
use DOMXPath;
use Language;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StableView;
use Message;
use RequestContext;
use Title;
use TitleFactory;
use User;
use WebRequest;

class StabilizePDFExport implements
	BSUEModulePDFgetPageHook,
	BSUEModulePDFBeforeAddingStyleBlocksHook,
	BSUEModulePDFbeforeGetPageHook
{
	/** @var StabilizationLookup */
	private $lookup;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var WebRequest */
	private $request;

	/** @var Config */
	private $config;

	/** @var User */
	private $user;

	/** @var StableView|null */
	private $view = null;

	/** @var Language */
	private $language;

	/**
	 * @param StabilizationLookup $stabilizationLookup
	 * @param TitleFactory $titleFactory
	 * @param Language $language
	 * @param Config $config
	 */
	public function __construct(
		StabilizationLookup $stabilizationLookup, TitleFactory $titleFactory,
		Language $language, Config $config
	) {
		$this->lookup = $stabilizationLookup;
		$this->titleFactory = $titleFactory;

		$this->language = $language;
		$this->config = $config;

		$this->request = RequestContext::getMain()->getRequest();
		$this->user = RequestContext::getMain()->getUser();
	}

	/**
	 * @inheritDoc
	 */
	public function onBSUEModulePDFBeforeAddingStyleBlocks( array &$template, array &$styleBlocks ): void {
		$base = dirname( __DIR__, 3 ) . '/resources';
		$styleBlocks[ 'ContentStabilization' ] = file_get_contents( "$base/stabilized-export.css" );
	}

	/**
	 * @inheritDoc
	 */
	public function onBSUEModulePDFbeforeGetPage( &$params ): void {
		$forceUnstable = $this->request->getBool( 'stable', true ) === false;
		// Get oldid from params
		$oldId = isset( $params['oldid'] ) ? (int)$params['oldid'] : null;
		if ( !$oldId ) {
			// if not set, get from request
			$oldId = $this->request->getInt( 'oldid', null );
		}
		$title = $this->titleFactory->newFromID( $params['article-id'] ?? 0 );

		if ( !( $title instanceof Title ) ) {
			return;
		}

		if ( !$title->canExist() ) {
			// Virtual namespace
			return;
		}
		if ( !$this->lookup->isStabilizationEnabled( $title->toPageIdentity() ) ) {
			return;
		}
		$stabilizationOptions = [
			'forceUnstable' => $forceUnstable,
		];
		if ( $oldId ) {
			$stabilizationOptions['upToRevision'] = $oldId;
		}
		$this->view = $this->lookup->getStableView( $title, $this->user, $stabilizationOptions );

		if ( !$this->view ) {
			return;
		}
		if ( !$this->view->getRevision() ) {
			// Cannot show anything
			$oldId = 0;
		} else {
			$oldId = $this->view->getRevision()->getId();
		}

		$params['oldid'] = $oldId;
		$params['stabilized'] = true;
	}

	/**
	 * @inheritDoc
	 */
	public function onBSUEModulePDFgetPage( Title $title, array &$page, array &$params, DOMXPath $DOMXPath ): void {
		if ( !$this->config->get( 'BlueSpiceUEModulePDFShowStabilizationTag' ) ) {
			return;
		}
		if ( !$this->lookup->isStabilizationEnabled( $title ) ) {
			return;
		}
		if ( !$this->view || !$this->view->getRevision() ) {
			return;
		}

		$lastStable = null;
		if ( $this->view->getStatus() === StableView::STATE_STABLE ) {
			$lastStable = $this->view->getLastStablePoint();
		}

		// Timestamp when stable point was added (time of approval)
		$lastStableTime = '';
		// Timestamp when the revision was created
		$lastStableRevisionTime = '';
		if ( $lastStable ) {
			$lastStableTime = $lastStable->getTime()->format( 'YmdHis' );
			$lastStableRevisionTime = $lastStable->getRevision()->getTimestamp();
		}

		$page['meta']['laststabledate'] = $this->formatTs( $lastStableTime );
		$page['meta']['stablerevisiondate'] = $this->formatTs( $lastStableRevisionTime );

		$revNode = $this->createRevisionInformationNode( $page, !!$lastStableTime );

		$page['firstheading-element']->parentNode->insertBefore(
			$revNode, $page['firstheading-element']->nextSibling
		);
	}

	private function createRevisionInformationNode( array $page, bool $isStable ): DOMElement {
		$dom = $page['dom'];
		$lastStableTime = $page['meta']['laststabledate'];
		$lastStableRevisionTime = $page['meta']['stablerevisiondate'];

		$revInfoNode = $dom->createElement( 'span' );

		if ( !$isStable ) {
			$dateNode = $dom->createElement(
				'span',
				" " . Message::newFromKey( 'contentstabilization-export-no-stable-date' )->escaped() );
			$dateNode->setAttribute( 'class', 'nostable' );
		} else {
			$dateNode = $dom->createElement( 'span', " " . $lastStableTime );
		}

		$stableTagText = Message::newFromKey( 'contentstabilization-export-laststable-tag-text' )->escaped();
		$stableTag = $dom->createElement( 'span', $stableTagText );
		$stableTag->setAttribute( 'class', 'contentstabilization-export-laststable-tag' );
		$stableTag->appendChild( $dateNode );

		$stableRevDateTagText = Message::newFromKey(
			'contentstabilization-export-stablerevisiondate-tag-text',
			$lastStableRevisionTime
		)->escaped();
		$stableRevDateTag = $dom->createElement( 'span', $stableRevDateTagText );
		$stableRevDateTag->setAttribute( 'class', 'contentstabilization-export' );

		$revInfoNode->appendChild( $stableTag );
		$revInfoNode->appendChild( $dom->createElement( 'span', " / " ) );
		$revInfoNode->appendChild( $stableRevDateTag );

		return $revInfoNode;
	}

	/**
	 * @param string $lastStableRevisionTime
	 *
	 * @return string
	 */
	private function formatTs( string $lastStableRevisionTime ): string {
		return $this->language->userTimeAndDate( $lastStableRevisionTime, $this->user );
	}
}
