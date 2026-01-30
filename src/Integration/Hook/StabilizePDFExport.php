<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use DOMDocument;
use DOMElement;
use DOMException;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
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

	/**
	 * @param StabilizationLookup $stabilizationLookup
	 * @param Language $language
	 * @param Config $config
	 * @param IContextSource|null $requestContext
	 */
	public function __construct(
		StabilizationLookup $stabilizationLookup,
		Language $language,
		Config $config,
		private ?IContextSource $requestContext = null
	) {
		$this->lookup = $stabilizationLookup;
		$this->language = $language;
		$this->config = $config;

		if ( $this->requestContext === null ) {
			$this->requestContext = RequestContext::getMain();
		}
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

		if ( isset( $params['rev-id'] ) ) {
			$params['upToRevision'] = $params['rev-id'];
		}

		if ( isset( $params['stable'] ) ) {
			// sanitize param value. Might have wrong value in manual written specification.
			if ( in_array( $params['stable'], [ '1', 1, 'true', true ] ) ) {
				$params['stable'] = '1';
			} else {
				$params['stable'] = '0';
			}

			if ( wfStringToBool( $params['stable'] ) === false ) {
				$params['forceUnstable'] = true;
			}
		}

		$this->view = $this->lookup->getStableView( $revisionRecord->getPage(), $userIdentity, $params );
		$revisionRecord = $this->view->getRevision();
	}

	/**
	 * @param DOMDocument $dom
	 * @param PageContext $context
	 *
	 * @return void
	 * @throws DOMException
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
		$revisionTimestamp = '';
		if ( $lastStable ) {
			$lastStableTime = $lastStable->getTime()->format( 'YmdHis' );
			$revisionTimestamp = $lastStable->getRevision()->getTimestamp();
		} else {
			$revisionTimestamp = $this->view->getRevision()->getTimestamp();
		}

		$stableTag = $dom->createElement(
			'span',
			Message::newFromKey( 'contentstabilization-export-laststable-tag-text' )
				->text() . ' '
		);

		$stableTag->setAttribute( 'class', 'contentstabilization-export-laststable-tag' );
		if ( $lastStableTime === '' ) {
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
				->params( $this->formatTs( $revisionTimestamp, $context->getUser() ) )
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
		if ( $firstHeading === null ) {
			return;
		}

		$container = $dom->createElement( 'div' );
		$container->setAttribute( 'class', 'contentstabilization-export-information' );

		$container->appendChild( $stableTag );
		$container->appendChild( $stableRevDateTag );

		$firstHeading->parentNode->insertBefore( $container, $firstHeading->nextSibling );
	}

	/**
	 * @param string $revisionTimestamp
	 * @param User $user
	 * @return string
	 */
	private function formatTs( string $revisionTimestamp, User $user ): string {
		return $this->language->userTimeAndDate( $revisionTimestamp, $user );
	}
}
