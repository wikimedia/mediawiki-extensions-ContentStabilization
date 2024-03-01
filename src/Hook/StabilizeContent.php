<?php

namespace MediaWiki\Extension\ContentStabilization\Hook;

use Article;
use ManualLogEntry;
use MediaWiki\Extension\ContentStabilization\ContentStabilizer;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StableFilePoint;
use MediaWiki\Extension\ContentStabilization\StableView;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\BeforeParserFetchFileAndTitleHook;
use MediaWiki\Hook\BeforeParserFetchTemplateRevisionRecordHook;
use MediaWiki\Hook\MediaWikiPerformActionHook;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\ArticleViewHeaderHook;
use MediaWiki\Page\Hook\ImagePageFindFileHook;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionRenderer;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserIdentity;
use OutputPage;
use Parser;
use ParserOptions;
use PermissionsError;
use Title;
use TitleFactory;

class StabilizeContent implements
	ArticleViewHeaderHook,
	BeforeParserFetchTemplateRevisionRecordHook,
	BeforeParserFetchFileAndTitleHook,
	PageDeleteCompleteHook,
	PageMoveCompleteHook,
	ImagePageFindFileHook,
	BeforePageDisplayHook,
	MediaWikiPerformActionHook
{

	/** @var StabilizationLookup */
	private $lookup;

	/** @var Parser */
	private $parser;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var ContentStabilizer */
	private $stabilizer;

	/** @var HookContainer */
	private $hookContainer;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var RevisionRenderer */
	private $revisionRenderer;

	/** @var StableView|null */
	private $view = null;

	/** @var array To avoid re-processing of inclusions */
	private $processedInclusions = [];

	/**
	 * @param StabilizationLookup $lookup
	 * @param Parser $parser
	 * @param RevisionLookup $revisionLookup
	 * @param ContentStabilizer $stabilizer
	 * @param HookContainer $hookContainer
	 * @param TitleFactory $titleFactory
	 * @param RevisionRenderer $revisionRenderer
	 */
	public function __construct(
		StabilizationLookup $lookup, Parser $parser, RevisionLookup $revisionLookup, ContentStabilizer $stabilizer,
		HookContainer $hookContainer, TitleFactory $titleFactory, RevisionRenderer $revisionRenderer
	) {
		$this->lookup = $lookup;
		$this->parser = $parser;
		$this->revisionLookup = $revisionLookup;
		$this->stabilizer = $stabilizer;
		$this->hookContainer = $hookContainer;
		$this->titleFactory = $titleFactory;
		$this->revisionRenderer = $revisionRenderer;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$action = $out->getRequest()->getVal( 'action', 'view' );
		if ( $action !== 'view' ) {
			return;
		}
		// Set noindex,nofollow for non-stable views
		if ( !$this->view ) {
			return;
		}
		if ( $this->view->getStatus() === StableView::STATE_STABLE ) {
			return;
		}
		if (
			$this->view->getStatus() === StableView::STATE_FIRST_UNSTABLE &&
			$this->lookup->isFirstUnstableAllowed()
		) {
			return;
		}

		$out->setRobotPolicy( 'noindex,nofollow' );
	}

	/**
	 * @inheritDoc
	 */
	public function onArticleViewHeader( $article, &$outputDone, &$pcache ) {
		$this->setViewFromArticle( $article );
		if ( !$this->view ) {
			return;
		}
		if ( !$this->view->getRevision() ) {
			$outputDone = true;
			throw new PermissionsError( null, [ 'badaccess-group0' ] );
		}

		if ( $article->getContext()->getRequest()->getBool( 'debug' ) ) {
			$this->outputStableViewInfo( $article->getContext()->getOutput() );
		}

		$revision = $this->view->getRevision();
		$options = $this->parser->getOptions() ?? ParserOptions::newFromContext( $article->getContext() );
		$options->setCurrentRevisionRecordCallback(
			static function ( LinkTarget $link, $parser = null ) use ( $revision ) {
				if ( $link instanceof PageIdentity ) {
					$page = $link;
				} else {
					$page = Title::castFromLinkTarget( $link );
				}
				if (
					$page->getDBkey() === $revision->getPage()->getDBkey() &&
					$page->getNamespace() === $revision->getPage()->getNamespace()
				) {
					return $revision;
				}
				return MediaWikiServices::getInstance()
					->getRevisionLookup()
					->getKnownCurrentRevision( $page );
			} );

		$renderedRev = $this->revisionRenderer->getRenderedRevision(
			$revision, $options, $this->view->getTargetUser()
		);
		$parserOutput = $renderedRev->getRevisionParserOutput();
		$outputDone = $parserOutput;
		$revisionUsed = $this->view->getRevision();

		$parserOutput->setRevisionTimestampUsed( $revisionUsed->getTimestamp() );
		$parserOutput->setCacheRevisionId( $revisionUsed->getId() );
		$parserOutput->setRevisionUsedSha1Base36( $revisionUsed->getSha1() );

		$pageTitle = $this->titleFactory->castFromPageIdentity( $revisionUsed->getPage() );
		$this->hookContainer->run(
			'ContentAlterParserOutput', [ $revision->getContent( SlotRecord::MAIN ), $pageTitle, &$parserOutput ]
		);
		$poOptions = [];
		$authority = $article->getContext()->getUser();
		if ( !$authority->probablyCan( 'edit', $pageTitle ) ) {
			$poOptions['enableSectionEditLinks'] = false;
		}
		$article->getContext()->getOutput()->addParserOutput( $parserOutput, $poOptions );
		$article->getContext()->getOutput()->setRevisionId( $revisionUsed->getId() );
	}

	/**
	 * @param OutputPage $out
	 *
	 * @return void
	 */
	private function outputStableViewInfo( OutputPage $out ) {
		$out->addHTML( '<h1>Showing version of the page based on this stable data</h1>' );
		$out->addHTML( '<pre>' . json_encode( $this->view->jsonSerialize(), JSON_PRETTY_PRINT ) . '</pre>' );
		$out->addHTML( '<h1>Page content</h1>' );
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforeParserFetchFileAndTitle( $parser, $nt, &$options, &$descQuery ) {
		if ( !$this->view ) {
			return;
		}
		if ( !$this->view->getRevision() ) {
			$options['broken'] = true;
			return;
		}
		// Remove NS_FILE namespace prefix
		$bits = explode( ':', $nt );
		array_shift( $bits );
		$filename = implode( ':', $bits );
		$filename = str_replace( ' ', '_', $filename );
		foreach ( $this->view->getInclusions()['images'] as $image ) {
			if ( $image['name'] === $filename ) {
				if ( !$image['timestamp'] ) {
					// No version to show found
					$options['broken'] = true;
					return;
				}
				$options['sha1'] = $image['sha1'];
				$options['time'] = $image['timestamp'];
				return;
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforeParserFetchTemplateRevisionRecord(
		?LinkTarget $contextTitle, LinkTarget $title, bool &$skip, ?RevisionRecord &$revRecord
	) {
		if ( !$this->view ) {
			return;
		}
		if ( !$this->view->getRevision() ) {
			$skip = true;
			return;
		}

		if ( !$this->pageEquals( $contextTitle, $this->view->getPage() ) ) {
			// Not checking for the page we are expecting, don't do anything
			return;
		}

		$key = $title->getNamespace() . $title->getDBkey();
		if ( $revRecord ) {
			$key .= ':' . $revRecord->getId();
		}
		if ( !in_array( $key, $this->processedInclusions ) ) {
			$this->processedInclusions[] = $key;
		} else {
			// Already processed this one
			return;
		}

		foreach ( $this->view->getInclusions()['transclusions'] as $transclusion ) {
			if (
				$transclusion['namespace'] === $title->getNamespace() &&
				$transclusion['title'] === $title->getDBkey()
			) {
				$selectedRevision = $revRecord;
				if ( !$selectedRevision || $selectedRevision->getId() !== $transclusion['revision'] ) {
					$replacement = $this->revisionLookup->getRevisionById( $transclusion['revision'] );
					if ( $replacement ) {
						$selectedRevision = $replacement;
					}
				}
				if ( !$selectedRevision ) {
					// NO revision to show
					$skip = true;
					return;
				}
				// Get stable view for transclusion
				$view = $this->lookup->getStableView(
					Title::newFromLinkTarget( $title )->toPageIdentity(),
					$this->view->getTargetUser(), [
						'upToRevision' => $selectedRevision->getId(),
						// Add marker for customized third-party code to know about the context
						'transclusionCheck' => true
				] );
				if ( $view ) {
					// Resource stabilized
					$selectedRevision = $view->getRevision();
				}

				$revRecord = $selectedRevision;
				$skip = $selectedRevision === null;
				return;
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onPageDeleteComplete(
		ProperPageIdentity $page, Authority $deleter, string $reason, int $pageID,
		RevisionRecord $deletedRev, ManualLogEntry $logEntry, int $archivedRevisionCount
	) {
		$this->stabilizer->removeStablePointsForPage( $page );
		$this->stabilizer->getInclusionManager()->removeStableInclusionsForPage( $page );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		// Do we need to do anything?
	}

	/**
	 * @inheritDoc
	 */
	public function onImagePageFindFile( $page, &$file, &$displayFile ) {
		if ( !$this->lookup->isStabilizationEnabled( $page->getContext()->getTitle() ) ) {
			return;
		}

		$this->setViewFromArticle( $page );
		if ( !$this->view ) {
			return;
		}
		if ( !$this->view->getRevision() ) {
			$displayFile = null;
			$file = null;
			return;
		}
		if (
			$this->view->getStatus() !== StableView::STATE_STABLE &&
			$this->lookup->canUserSeeUnstable( $page->getContext()->getUser() )
		) {
			// User can see unstable, so don't override the file
			return;
		}

		$point = $this->view->getLastStablePoint();
		if ( !( $point instanceof StableFilePoint ) ) {
			$file = null;
			$displayFile = null;
			return;
		}
		$displayFile = $point->getFile();
	}

	/**
	 * @param Article $article
	 *
	 * @return void
	 */
	private function setViewFromArticle( Article $article ) {
		$this->view = $this->lookup->getStableViewFromContext( $article->getContext() );
	}

	/**
	 * @param PageIdentity $page
	 * @param UserIdentity $user
	 *
	 * @return bool
	 */
	private function shouldSwitchToLatestForEdit( PageIdentity $page, UserIdentity $user ): bool {
		if ( !$page->exists() ) {
			return false;
		}
		if ( !$this->view ) {
			return false;
		}
		if ( !$this->view->getRevision() ) {
			return false;
		}
		$requested = $this->view->getRevision();
		$stableFromView = $this->view->getLastStablePoint();
		$latestStable = $this->lookup->getLastStablePoint( $page );
		if (
			$stableFromView && $latestStable &&
			$stableFromView->getRevision()->getId() < $latestStable->getRevision()->getId()
		) {
			// If we are viewing an old revision, that has a stable point afterwards, edit that one
			return false;
		}
		if ( $this->view->isStable() && !$requested->isCurrent() ) {
			// If we are viewing stable but there is a draft, edit that one, if user can see it
			return $this->lookup->canUserSeeUnstable( $user ) ? true : false;
		}

		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function onMediaWikiPerformAction( $output, $article, $title, $user, $request, $mediaWiki ) {
		if ( !$this->lookup->isStabilizationEnabled( $title ) ) {
			return true;
		}
		$this->setViewFromArticle( $article );

		$action = $request->getText( 'veaction', $request->getText( 'action', 'view' ) );
		if ( $action === 'edit' ) {
			if ( !$this->shouldSwitchToLatestForEdit( $title, $user ) ) {
				return true;
			}
			// Replace revision to edit, if needed
			$request->setVal( 'stable', 0 );
			$request->unsetVal( 'oldid' );
		}

		if ( $action === 'raw' ) {
			if (
				$this->view && $this->view->getRevision() &&
				( $this->view->getStatus() === StableView::STATE_STABLE || $this->lookup->canUserSeeUnstable( $user ) )
			) {
				$request->setVal( 'oldid', $this->view->getRevision()->getId() );
			} else {
				$output->showPermissionsErrorPage( [ 'badaccess-group0' ] );
				return false;
			}
		}
		return true;
	}

	/**
	 * @param LinkTarget|PageIdentity $a
	 * @param LinkTarget|PageIdentity $b
	 *
	 * @return bool
	 */
	private function pageEquals( $a, $b ): bool {
		return $a && $b && $a->getNamespace() === $b->getNamespace() && $a->getDBkey() === $b->getDBkey();
	}
}
