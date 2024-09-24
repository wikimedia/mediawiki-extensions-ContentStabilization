<?php

namespace MediaWiki\Extension\ContentStabilization\Hook;

use MediaWiki\Extension\ContentStabilization\InclusionManager;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StableView;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Revision\RevisionStore;
use Message;
use Parser;
use PPFrame;
use RequestContext;
use TitleFactory;
use Wikimedia\Rdbms\ILoadBalancer;

class AddDocumentStateTag implements ParserFirstCallInitHook {

	/**
	 * @var ILoadBalancer
	 */
	private $lb;

	/**
	 * @var TitleFactory
	 */
	private $titleFactory;

	/**
	 * @var RevisionStore
	 */
	private $revisionStore;

	/**
	 * @var StabilizationLookup
	 */
	private $lookup;

	/**
	 * @var InclusionManager
	 */
	private $inclusionManager;

	/**
	 * @param ILoadBalancer $lb
	 * @param TitleFactory $titleFactory
	 * @param RevisionStore $revisionStore
	 * @param StabilizationLookup $stabilizationLookup
	 * @param InclusionManager $inclusionManager
	 */
	public function __construct(
		ILoadBalancer $lb,
		TitleFactory $titleFactory,
		RevisionStore $revisionStore,
		StabilizationLookup $stabilizationLookup,
		InclusionManager $inclusionManager
	) {
		$this->lb = $lb;
		$this->titleFactory = $titleFactory;
		$this->revisionStore = $revisionStore;
		$this->lookup = $stabilizationLookup;
		$this->inclusionManager = $inclusionManager;
	}

	/**
	 * @inheritDoc
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'documentstate', [ $this, 'renderDocumentStateTag' ] );
	}

	/**
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public function renderDocumentStateTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		// Replace variables like "{{FULLPAGENAME}}" or "{{REVISIONID}}"
		$pageName = $parser->recursivePreprocess( $args['page'] );
		$revisionId = (int)$parser->recursivePreprocess( $args['revision'] );

		if ( !$pageName && !$revisionId ) {
			return Message::newFromKey( 'contentstabilization-documentstate-no-valid-args' )->text();
		}

		$pageId = null;
		if ( $pageName ) {
			$title = $this->titleFactory->newFromText( $pageName );
			if ( $title ) {
				$pageId = $title->getId();

				if ( !$revisionId ) {
					$revisionId = $title->getLatestRevID();
				}
			}
		} else {
			// We need page ID to collect all approved revisions
			// So if page name was not specified - get page ID from provided revision ID
			$revision = $this->revisionStore->getRevisionById( $revisionId );
			if ( $revision ) {
				$pageId = $revision->getPageId();
			}
		}

		if ( !$pageId ) {
			return Message::newFromKey( 'contentstabilization-documentstate-no-valid-page-id' )->text();
		}

		$dbr = $this->lb->getConnection( DB_REPLICA );

		$queryBuilder = $dbr->newSelectQueryBuilder();
		$queryBuilder->select( 'sp_revision' )
			->from( 'stable_points' )
			->where( [
				'sp_page' => $pageId
			] )
			->orderBy( 'sp_revision', 'DESC' );

		$res = $queryBuilder->fetchResultSet();

		$revisions = [];
		foreach ( $res as $row ) {
			$revisions[] = (int)$row->sp_revision;
		}

		$state = StableView::STATE_UNSTABLE;

		if ( !$revisions ) {
			// No approved revisions - "First draft"
			$state = StableView::STATE_FIRST_UNSTABLE;
		} else {
			foreach ( $revisions as $revId ) {
				if ( $revId === $revisionId ) {
					// We found specified revision among approved revisions
					// So - "Stable"
					$state = StableView::STATE_STABLE;

					break;
				}
			}
		}

		$request = RequestContext::getMain()->getRequest();

		if (
			$state === StableView::STATE_STABLE &&
			$request->getVal( 'stable' ) === '0' &&
			$this->checkImplicitDraft( $pageId, $revisionId )
		) {
			$state = StableView::STATE_IMPLICIT_UNSTABLE;
		}

		// contentstabilization-status-first-unstable
		// contentstabilization-status-unstable
		// contentstabilization-status-implicit-unstable
		// contentstabilization-status-stable
		$msg = Message::newFromKey( "contentstabilization-status-$state" )->inContentLanguage();
		return $msg->text();
	}

	/**
	 * @param int $pageId
	 * @param int $revisionId
	 * @return bool
	 */
	private function checkImplicitDraft( int $pageId, int $revisionId ): bool {
		$user = RequestContext::getMain()->getUser();
		$title = $this->titleFactory->newFromID( $pageId );

		$canSeeDrafts = $this->lookup->canUserSeeUnstable( $user );

		$lastStablePoint = $this->lookup->getLastStablePoint( $title, $revisionId );
		$latestStableRevId = $lastStablePoint->getRevision()->getId();

		$hasNewerStable = $latestStableRevId > $revisionId;

		// If it is the latest stable version, check if inclusions are in sync with current
		if ( $hasNewerStable ) {
			$inSync = true;
		} else {
			$outOfSyncInclusions = $this->inclusionManager->getSyncDifference( $lastStablePoint );
			$inSync = empty( $outOfSyncInclusions );
		}

		// If viewing the latest stable, but it's not forced, and it's not in sync, set correct state
		if ( !$hasNewerStable && !$inSync && $canSeeDrafts ) {
			return true;
		}

		return false;
	}
}
