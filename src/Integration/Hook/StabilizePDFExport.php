<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use BlueSpice\UEModulePDF\Hook\BSUEModulePDFBeforeAddingStyleBlocksHook;
use BlueSpice\UEModulePDF\Hook\BSUEModulePDFbeforeGetPageHook;
use BlueSpice\UEModulePDF\Hook\BSUEModulePDFgetPageHook;
use Config;
use DOMXPath;
use Language;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StableView;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use RequestContext;
use TitleFactory;
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

		$stableTag = $page['dom']->createElement(
			'span',
			\Message::newFromKey( 'contentstabilization-export-laststable-tag-text' )
				->text()
		) . ' ';

		$stableTag->setAttribute( 'class', 'contentstabilization-export-laststable-tag' );
		if ( !$lastStableTime ) {
			$dateNode = $page['dom']->createElement(
				'span',
				\Message::newFromKey( 'contentstabilization-export-no-stable-date' )
					->plain()
			);
			$dateNode->setAttribute( 'class', 'nostable' );
		} else {
			$dateNode = $page['dom']->createTextNode( $page['meta']['laststabledate'] );
		}

		$stableTag->appendChild( $dateNode );

		$stableRevDateTag = $page['dom']->createElement(
			'span',
			' / ' . \Message::newFromKey( 'contentstabilization-export-stablerevisiondate-tag-text' )
				->params( $page['meta']['stablerevisiondate'] )
				->text()
		);
		$stableRevDateTag->setAttribute( 'class', 'contentstabilization-export' );

		$page['firstheading-element']->parentNode->insertBefore(
			$stableRevDateTag, $page['firstheading-element']->nextSibling
		);

		$page['firstheading-element']->parentNode->insertBefore(
			$stableTag, $page['firstheading-element']->nextSibling
		);
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
