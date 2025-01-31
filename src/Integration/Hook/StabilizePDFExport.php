<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use DOMDocument;
use DOMElement;
use MediaWiki\Config\Config;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StableView;
use MediaWiki\Extension\PDFCreator\Utility\PageContext;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;

class StabilizePDFExport {
	/** @var StabilizationLookup */
	private $lookup;

	/** @var Config */
	private $config;

	/** @var StableView|null */
	private $view = null;

	/** @var Language */
	private $language;

	/** @var array */
	private $params;

	/**
	 * @param StabilizationLookup $stabilizationLookup
	 * @param Language $language
	 * @param Config $config
	 */
	public function __construct(
		StabilizationLookup $stabilizationLookup, Language $language, Config $config
	) {
		$this->lookup = $stabilizationLookup;
		$this->language = $language;
		$this->config = $config;
	}

	/**
	 * @param RevisionRecord &$revisionRecord
	 * @param UserIdentity $userIdentity
	 * @param array $params
	 * @return void
	 */
	public function onPDFCreatorAfterSetRevision(
		RevisionRecord &$revisionRecord, UserIdentity $userIdentity, array $params
	): void {
		if ( !$this->lookup->isStabilizationEnabled( $revisionRecord->getPage() ) ) {
			return;
		}
		$this->params = $params;
		if ( isset( $this->params['stable'] ) ) {
			$stable = $this->params['stable'];
		}
		if ( !in_array( $stable, [ 0, false, 'false' ] ) ) {
			return;
		}
		$this->params['forceUnstable'] = false;

		$this->view = $this->lookup->getStableView( $revisionRecord->getPage(), $userIdentity, $this->params );
		$revisionRecord = $this->view->getRevision();
	}

	/**
	 * @param DOMDocument $dom
	 * @param PageContext $context
	 * @return void
	 */
	public function onPDFCreatorAfterGetDOMDocument( DOMDocument $dom, PageContext $context ): void {
		if ( !$this->config->get( 'ContentStabilizationPDFCreatorShowStabilizationTag' ) ) {
			return;
		}

		if ( !$context->getTitle()->canExist() ) {
			// Virtual namespace
			return;
		}

		if ( !$this->lookup->isStabilizationEnabled( $context->getTitle() ) ) {
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

		$stableTag = $dom->createElement(
			'span',
			Message::newFromKey( 'contentstabilization-export-laststable-tag-text' )
				->text() . ' '
		);

		$stableTag->setAttribute( 'class', 'contentstabilization-export-laststable-tag' );
		if ( !$lastStableTime ) {
			$dateNode = $dom->createElement(
				'span',
				Message::newFromKey( 'contentstabilization-export-no-stable-date' )
					->plain()
			);
			$dateNode->setAttribute( 'class', 'nostable' );
		} else {
			$dateNode = $dom->createTextNode( $this->formatTs( $lastStableTime, $context->getUser() ) );
		}

		$stableTag->appendChild( $dateNode );

		$stableRevDateTag = $dom->createElement(
			'span',
			' / ' . Message::newFromKey( 'contentstabilization-export-stablerevisiondate-tag-text' )
				->params( $this->formatTs( $lastStableRevisionTime, $context->getUser() ) )
				->text()
		);
		$stableRevDateTag->setAttribute( 'class', 'contentstabilization-export' );

		$headings = $dom->getElementsByTagName( 'h1' );
		$firstHeading = null;
		foreach ( $headings as $heading ) {
			if ( $heading instanceof DOMElement === false ) {
				continue;
			}
			if ( !$heading->hasAttribute( 'class' ) ) {
				continue;
			}
			$classes = $heading->getAttribute( 'class' );
			if ( strpos( $classes, 'firstHeading' ) === false ) {
				continue;
			}
			$firstHeading = $heading;
			break;
		}
		if ( $heading === null ) {
			return;
		}

		$firstHeading->parentNode->insertBefore( $stableRevDateTag, $firstHeading->nextSibling );
		$firstHeading->parentNode->insertBefore( $stableTag, $firstHeading->nextSibling );
	}

	/**
	 * @inheritDoc
	 */
	public function onBSUEModulePDFBeforeAddingStyleBlocks( array &$template, array &$styleBlocks ): void {
		$base = dirname( __DIR__, 3 ) . '/resources';
		$styleBlocks[ 'ContentStabilization' ] = file_get_contents( "$base/stabilized-export.css" );
	}

	/**
	 * @param string $lastStableRevisionTime
	 * @param User $user
	 * @return string
	 */
	private function formatTs( string $lastStableRevisionTime, User $user ): string {
		return $this->language->userTimeAndDate( $lastStableRevisionTime, $user );
	}
}
