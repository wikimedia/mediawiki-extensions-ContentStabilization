<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use BlueSpice\UEModuleBookPDF\Hook\BSBookshelfExportBeforeArticlesHook;
use BlueSpice\UEModulePDF\Hook\BSUEModulePDFBeforeAddingStyleBlocksHook;
use BlueSpice\UniversalExport\ExportSpecification;
use Config;
use DOMElement;
use Language;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use PageProps;

class StabilizeBookExport implements
	BSBookshelfExportBeforeArticlesHook,
	BSUEModulePDFBeforeAddingStyleBlocksHook
{
	/** @var StabilizationLookup */
	private $lookup;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var Config */
	private $config;

	/** @var Language */
	private $language;

	/** @var PageProps */
	private $pageProps;

	/** @var User */
	private $user;

	/** @var HookContainer */
	private $hookContainer;

	/**
	 * @param StabilizationLookup $stabilizationLookup
	 * @param TitleFactory $titleFactory
	 * @param Language $language
	 * @param PageProps $pageProps
	 * @param Config $config
	 * @param HookContainer $hookContainer
	 */
	public function __construct(
		StabilizationLookup $stabilizationLookup, TitleFactory $titleFactory,
		Language $language, PageProps $pageProps, Config $config, HookContainer $hookContainer
	) {
		$this->lookup = $stabilizationLookup;
		$this->titleFactory = $titleFactory;

		$this->language = $language;
		$this->pageProps = $pageProps;
		$this->config = $config;
		$this->hookContainer = $hookContainer;

		$this->user = RequestContext::getMain()->getUser();
	}

	/**
	 * @inheritDoc
	 */
	public function onBSUEModulePDFBeforeAddingStyleBlocks( array &$template, array &$styleBlocks ): void {
		$base = dirname( __DIR__, 3 ) . '/resources';
		$styleBlocks[ 'ContentStabilizationBookshelf' ] = file_get_contents( "$base/stabilized-export.css" );
	}

	/**
	 * @param array &$template
	 * @param array &$bookPage
	 * @param array &$articles
	 * @param ExportSpecification $specification
	 *
	 * @return void
	 * @throws \MWException
	 * @throws \Wikimedia\RequestTimeout\TimeoutException
	 */
	public function onBSBookshelfExportBeforeArticles(
		array &$template, array &$bookPage, array &$articles, ExportSpecification $specification
	): void {
		// List pages that are stable
		$showStable = $this->config->get( 'BlueSpiceBookshelfExportListStable' );
		// List unstable pages
		$showUnstable = $this->config->get( 'BlueSpiceBookshelfExportListUnstable' );
		// List pages that do not have stabilization enabled
		$showDisabled = $this->config->get( 'BlueSpiceBookshelfExportListDisabled' );

		if ( !$showStable && !$showUnstable && !$showDisabled ) {
			// In this case we do not need to do anything
			return;
		}

		// Let's add the "FlaggedRevs History page"
		$stabilizationHistoryPage = $template['dom']->createElement( 'div' );
		$stabilizationHistoryPage->setAttribute(
			'class',
			'bs-section bs-custompage bs-stabilizationhistorypage bs-stabilizationhistorypage'
		);
		$template[ 'content-elements' ][ 'content' ]->parentNode->insertBefore(
			$stabilizationHistoryPage, $template[ 'content-elements' ][ 'content' ]
		);

		$stable = [];
		$unstable = [];
		$disabled = [];

		$displayTitles = [];
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'bsg' );
		$useBookDisplayTitle = $config->get( 'BookshelfTitleDisplayText' );
		foreach ( $articles as $article ) {
			$title = $this->titleFactory->newFromText( $article['title'] );
			if ( !( $title instanceof Title ) || !$title->exists() ) {
				continue;
			}
			if ( $useBookDisplayTitle ) {
				$displayTitles[$title->getArticleID()] = $article['display-title'];
			} else {
				$displayTitles[$title->getArticleID()] = $this->getDisplayTitle( $title );
			}
			// If the articles namespace does not have stabilization enabled, we skip it
			if ( !$this->lookup->isStabilizationEnabled( $title ) ) {
				$disabled[] = $title;
				continue;
			}
			$stablePoint = $this->lookup->getLastStablePoint( $title->toPageIdentity() );
			if ( $stablePoint ) {
				$stable[] = $stablePoint;
			} else {
				$unstable[] = $title;
			}
		}

		// Now, after fetching all necessary data we build the tables
		$stableDiv = $showStable ? $this->getStableDiv( $template, $stable, $displayTitles ) : null;
		$noStableDiv = $showUnstable ? $this->getUnstableDiv( $template, $unstable, $displayTitles ) : null;
		$notEnabledDiv = $showDisabled ? $this->getDisabledDiv( $template, $disabled, $displayTitles ) : null;

		$this->runLegacyHooks(
			$template, $bookPage, $articles, $showStable, $showUnstable,
			$showDisabled, $stable, $unstable, $disabled
		);
		$this->hookContainer->run(
			'BookshelfExportBeforeHistoryPage',
			[
				&$template, &$bookPage, &$articles, &$showStable, &$showUnstable,
				&$showDisabled, &$stable, &$unstable, &$disabled
			]
		);

		// Finally we add the tables to the page
		if ( $stableDiv != null ) {
			$stabilizationHistoryPage->appendChild( $stableDiv );
		}
		if ( $noStableDiv != null ) {
			$stabilizationHistoryPage->appendChild( $noStableDiv );
		}
		if ( $notEnabledDiv != null ) {
			$stabilizationHistoryPage->appendChild( $notEnabledDiv );
		}
	}

	/**
	 * @param Title $title
	 * @return string
	 */
	private function getDisplayTitle( Title $title ): string {
		$pageProperties = [];
		$pageProps = $this->pageProps->getAllProperties( $title );

		$id = $title->getArticleID();

		if ( isset( $pageProps[$id] ) ) {
			$pageProperties = $pageProps[$id];
		}

		if ( isset( $pageProperties['displaytitle'] ) ) {
			return $pageProperties['displaytitle'];
		}

		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'bsg' );
		if ( $config->get( 'BookshelfSupressBookNS' ) ) {
			return $title->getText();
		}

		return $title->getPrefixedText();
	}

	/**
	 * @param array &$template
	 * @param array &$bookPage
	 * @param array &$articles
	 * @param bool &$showStable
	 * @param bool &$showUnstable
	 * @param bool &$showDisabled
	 * @param array &$stable
	 * @param array &$unstable
	 * @param array &$disabled
	 *
	 * @return void
	 */
	private function runLegacyHooks(
		&$template, &$bookPage, &$articles, &$showStable, &$showUnstable,
		&$showDisabled, &$stable, &$unstable, &$disabled
	) {
		$this->hookContainer->run(
			'BSFlaggedRecsConnectorBookshelfBeforeHistoryPage',
			[
				&$template, &$bookPage, &$articles, &$showStable, &$showUnstable,
				&$showDisabled, &$stable, &$unstable, &$disabled
			], [
				'deprecatedVersion' => '4.3'
			]
		);
		$this->hookContainer->run(
			'BSFRCBookshelfBeforeHistoryPage',
			[
				&$template, &$bookPage, &$articles, &$showStable, &$showUnstable,
				&$showDisabled, &$stable, &$unstable, &$disabled
			], [
				'deprecatedVersion' => '4.3'
			]
		);
	}

	/**
	 * @param array $template
	 * @param array $stable
	 * @param array $displayTitles
	 *
	 * @return DOMElement|null
	 */
	private function getStableDiv( array $template, array $stable, array $displayTitles ): ?DOMElement {
		if ( empty( $stable ) ) {
			return null;
		}
		$div = $template[ 'dom' ]->createElement( 'div' );
		$div->setAttribute( 'class', 'contentstabilization-stabilizationhistory-recent-changes' );
		$div->appendChild( $template[ 'dom' ]->createElement(
			'h2',
			wfMessage( 'contentstabilization-stabilizationhistory-stableversionstabletitle' )->plain()
		) );

		$stableVersionsTable = $template[ 'dom' ]->createElement( 'table' );
		$stableVersionsTable->setAttribute( 'width', '100%' );
		$stableVersionsTable->setAttribute( 'class', 'contentstabilization-stabilizationhistory-stables-list' );
		$div->appendChild( $stableVersionsTable );

		$thead = $stableVersionsTable->appendChild( $template[ 'dom' ]->createElement( 'thead' ) );
		$tbody = $stableVersionsTable->appendChild( $template[ 'dom' ]->createElement( 'tbody' ) );
		$throw = $thead->appendChild( $template[ 'dom' ]->createElement( 'tr' ) );
		$throw->appendChild( $template[ 'dom' ]->createElement(
			'th',
			wfMessage( 'contentstabilization-stabilizationhistory-stabledate' )->plain()
		) );
		$throw->appendChild( $template[ 'dom' ]->createElement(
			'th',
			wfMessage( 'contentstabilization-stabilizationhistory-title' )->plain()
		) );
		$throw->appendChild( $template[ 'dom' ]->createElement(
			'th',
			wfMessage( 'contentstabilization-stabilizationhistory-comment' )->plain()
		) );

		$cssClass = 'odd';
		/** @var StablePoint $stablePoint */
		foreach ( $stable as $stablePoint ) {
			$date = $this->language->userDate( $stablePoint->getRevision()->getTimestamp(), $this->user );
			$displayTitle = $displayTitles[$stablePoint->getPage()->getId()];

			$tRow = $template[ 'dom' ]->createElement( 'tr' );
			$tRow->appendChild( $template[ 'dom' ]->createElement( 'td', $date ) );
			$tRow->appendChild( $template[ 'dom' ]->createElement( 'td', $displayTitle ) );
			$tRow->appendChild( $template[ 'dom' ]->createElement( 'td', $stablePoint->getComment() ) );
			$tRow->setAttribute( 'class', $cssClass );

			$tbody->appendChild( $tRow );
			$cssClass = ( $cssClass == 'odd' ) ? 'even' : 'odd';
		}
		return $div;
	}

	/**
	 * @param array $template
	 * @param array $unstable
	 * @param array $displayTitles
	 *
	 * @return mixed
	 */
	private function getUnstableDiv( array $template, array $unstable, array $displayTitles ): ?DOMElement {
		if ( empty( $unstable ) ) {
			return null;
		}
		$div = $template[ 'dom' ]->createElement( 'div' );
		$div->setAttribute( 'class', 'contentstabilization-stabilizationhistory-no-stables' );
		$div->appendChild( $template[ 'dom' ]->createElement(
			'h2',
			wfMessage( 'contentstabilization-stabilizationhistory-nostableversionstabletitle' )->plain()
		) );

		$table = $template[ 'dom' ]->createElement( 'table' );
		$table->setAttribute( 'width', '100%' );
		$table->setAttribute( 'class', 'contentstabilization-stabilizationhistory-no-stables-list' );
		$div->appendChild( $table );

		$this->appendSimpleTable( $template, $table, $unstable, $displayTitles );

		return $div;
	}

	/**
	 * @param array $template
	 * @param array $disabled
	 * @param array $displayTitles
	 *
	 * @return mixed
	 */
	private function getDisabledDiv( array $template, array $disabled, array $displayTitles ) {
		if ( empty( $disabled ) ) {
			return null;
		}
		$div = $template[ 'dom' ]->createElement( 'div' );
		$div->setAttribute( 'class', 'contentstabilization-stabilizationhistory-no-flaggedrevs' );
		$div->appendChild( $template[ 'dom' ]->createElement(
			'h2',
			wfMessage( 'contentstabilization-stabilizationhistory-noflaggedrevstitle' )->plain()
		) );

		$disabledTable = $template[ 'dom' ]->createElement( 'table' );
		$disabledTable->setAttribute( 'width', '100%' );
		$disabledTable->setAttribute( 'class', 'contentstabilization-stabilizationhistory-no-flaggedrevs-list' );
		$this->appendSimpleTable( $template, $disabledTable, $disabled, $displayTitles );
		$div->appendChild( $disabledTable );
		return $div;
	}

	/**
	 * @param array $template
	 * @param DOMElement $table
	 * @param array $titles
	 * @param array $displayTitles
	 *
	 * @return void
	 */
	private function appendSimpleTable( $template, $table, $titles, $displayTitles ) {
		$cssClass = 'odd';
		foreach ( $titles as $title ) {
			$tRow = $template[ 'dom' ]->createElement( 'tr' );
			$tRow->appendChild( $template[ 'dom' ]->createElement( 'td', $displayTitles[$title->getArticleID()] ) );
			$tRow->setAttribute( 'class', $cssClass );

			$table->appendChild( $tRow );
			$cssClass = ( $cssClass == 'odd' ) ? 'even' : 'odd';
		}
	}
}
