<?php

namespace MediaWiki\Extension\ContentStabilization;

use File;
use MediaWiki\Config\Config;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\ContentStabilization\Storage\StablePointStore;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Request\WebRequest;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use ObjectCacheFactory;

class StabilizationLookup {
	/** @var StablePointStore */
	private $store;

	/** @var InclusionManager */
	private $inclusionManager;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var UserGroupManager */
	private $userGroupManager;

	/** @var Config */
	private $config;

	/** @var array */
	private $stableViewCache = [];

	/** @var bool */
	private $useCache = true;

	/** @var HookContainer */
	private $hookContainer;

	/** @var ObjectCacheFactory */
	private $objectCacheFactory;

	/**
	 * @param StablePointStore $store
	 * @param InclusionManager $inclusionManager
	 * @param RevisionStore $revisionStore
	 * @param UserGroupManager $userGroupManager
	 * @param Config $config
	 * @param HookContainer $hookContainer
	 * @param ObjectCacheFactory $objectCacheFactory
	 */
	public function __construct(
		StablePointStore $store, InclusionManager $inclusionManager, RevisionStore $revisionStore,
		UserGroupManager $userGroupManager, Config $config, HookContainer $hookContainer,
		ObjectCacheFactory $objectCacheFactory
	) {
		$this->store = $store;
		$this->inclusionManager = $inclusionManager;
		$this->revisionStore = $revisionStore;
		$this->userGroupManager = $userGroupManager;
		$this->config = $config;
		$this->hookContainer = $hookContainer;
		$this->objectCacheFactory = $objectCacheFactory;
	}

	/**
	 * @param bool $useCache
	 *
	 * @return void
	 */
	public function setUseCache( bool $useCache ) {
		$this->useCache = $useCache;
	}

	/**
	 * @return bool
	 */
	public function isFirstUnstableAllowed(): bool {
		return (bool)$this->config->get( 'AllowFirstUnstable' );
	}

	/**
	 * @param PageIdentity $page
	 *
	 * @return StablePoint[]
	 */
	public function getStablePointsForPage( PageIdentity $page ): array {
		$points = $this->store->query( [ 'sp_page' => $page->getId() ], __METHOD__ );
		return array_map( [ $this, 'decorateWithInclusions' ], $points );
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return StablePoint|null
	 */
	public function getStablePointForRevision( RevisionRecord $revisionRecord ): ?StablePoint {
		return $this->getStablePointForRevisionId( $revisionRecord->getId() );
	}

	/**
	 * @param int $revisionId
	 *
	 * @return StablePoint|null
	 */
	public function getStablePointForRevisionId( int $revisionId ): ?StablePoint {
		$point = $this->store->getLatestMatchingPoint( [ 'sp_revision' => $revisionId ] );
		return $this->decorateWithInclusions( $point );
	}

	/**
	 * @param PageIdentity $page
	 * @param RevisionRecord|int|null $upToRevision RevisionRecord object or id, or null for latest
	 *
	 * @return StablePoint|null
	 */
	public function getLastStablePoint( PageIdentity $page, $upToRevision = null ): ?StablePoint {
		$point = $this->getLastRawStablePoint( $page, $upToRevision );
		return $this->decorateWithInclusions( $point );
	}

	/**
	 * @param PageIdentity $page
	 * @param RevisionRecord|int|null $upToRevision
	 * @return StablePoint|null
	 */
	public function getLastRawStablePoint( PageIdentity $page, $upToRevision = null ): ?StablePoint {
		$conditions = [ 'sp_page' => $page->getId() ];
		$this->addUpToRevisionCondition( $conditions, $upToRevision );
		return $this->store->getLatestMatchingPoint( $conditions );
	}

	/**
	 * @param PageIdentity $page
	 * @param RevisionRecord|int|null $upToRevision
	 * @return RevisionRecord|null
	 */
	public function getLastStableRevision( PageIdentity $page, $upToRevision = null ): ?RevisionRecord {
		$point = $this->getLastRawStablePoint( $page, $upToRevision );
		if ( !$point ) {
			return null;
		}
		return $point->getRevision();
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return bool
	 */
	public function isStableRevision( RevisionRecord $revisionRecord ): bool {
		$stablePoint = $this->getLastRawStablePoint( $revisionRecord->getPage(), $revisionRecord );
		if ( !$stablePoint ) {
			return false;
		}
		return $stablePoint->getRevision()->getId() === $revisionRecord->getId();
	}

	/**
	 * @param PageIdentity $page
	 * @return array
	 */
	public function getPendingUnstableRevisions( PageIdentity $page ): array {
		$unstable = [];
		if ( $this->hasStable( $page ) ) {
			$rev = $this->getLastStableRevision( $page );
		} else {
			$rev = $this->revisionStore->getFirstRevision( $page );
			$unstable[] = $rev;
		}
		while ( $rev ) {
			$rev = $this->revisionStore->getNextRevision( $rev );
			if ( !$rev || $this->isStableRevision( $rev ) ) {
				break;
			}
			$unstable[] = $rev;
		}
		return $unstable;
	}

	/**
	 * @param IContextSource $context
	 *
	 * @return StableView|null
	 */
	public function getStableViewFromContext( IContextSource $context ): ?StableView {
		if ( $context->getTitle() === null || !$context->getTitle()->canExist() ) {
			return null;
		}
		$oldId = $context->getRequest()->getInt( 'oldid' );
		$params = [];
		if ( $oldId > 0 ) {
			$params['upToRevision'] = $oldId;
		}

		// Default true to force stable view by default
		$explicitlyStable = $this->getStableParamFromRequest( $context->getRequest() );
		if ( $explicitlyStable !== null ) {
			$params['forceUnstable'] = !$explicitlyStable;
		}

		return $this->getStableView( $context->getTitle()->toPageIdentity(), $context->getUser(), $params );
	}

	/**
	 * @param WebRequest $request
	 *
	 * @return bool|null null if not set
	 */
	public function getStableParamFromRequest( WebRequest $request ): ?bool {
		$queryParams = $request->getValueNames();
		if ( !in_array( 'stable', $queryParams ) ) {
			return null;
		}
		return $request->getBool( 'stable' );
	}

	/**
	 * Select revision and includes to show to a user
	 * This method is written purposely verbose to make it easier to follow
	 *
	 * @param PageIdentity $page
	 * @param UserIdentity|null $forUser
	 * @param array|null $options
	 * * upToRevision: RevisionRecord object or id, or null for latest
	 * * forceUnstable: bool, whether to force stable view even if user can see drafts
	 * If user cannot see, will return an earlier one, but never later
	 *
	 * @return StableView|null if not applicable
	 */
	public function getStableView(
		PageIdentity $page, ?UserIdentity $forUser = null, ?array $options = []
	): ?StableView {
		if ( !$page->exists() ) {
			return null;
		}
		if ( !$this->isStabilizationEnabled( $page ) ) {
			return null;
		}
		$userId = $forUser ? $forUser->getId() : -1;
		$optionsJson = json_encode( $options );
		$optionsHash = md5( $optionsJson );
		$cacheKey = "{$page->getId()}:$userId:{$optionsHash}";
		$showingOld = false;
		$explicitlyRequestedRev = false;

		if ( !$this->useCache || !isset( $this->stableViewCache[$cacheKey] ) ) {
			$upToRevision = $options['upToRevision'] ?? null;
			if ( $upToRevision ) {
				$explicitlyRequestedRev = true;
			}
			$forceStable = true;
			// Check if stable or draft is explicitly requested
			$explicitStableState = isset( $options['forceUnstable'] );
			if ( $explicitStableState && $options['forceUnstable'] ) {
				$forceStable = false;
			}
			if ( !$this->hasStable( $page ) ) {
				// No sense in forcing the stable version if there isnt any
				$forceStable = false;
			}

			// Get revision we are requested to show
			$selected = $this->providedRevisionOrLatest( $page, $upToRevision );
			if ( !$selected ) {
				$this->stableViewCache[$cacheKey] = null;
				return null;
			}

			if ( !$selected->isCurrent() || $explicitlyRequestedRev ) {
				// Requesting old revision, show that if possible
				$forceStable = $explicitStableState ? $forceStable : false;
				$showingOld = true;
			}
			$lastStable = $this->getLastStablePoint( $page, $selected );
			if ( $forceStable ) {
				$selected = $lastStable ? $lastStable->getRevision() : null;
			}

			// At this point, we know there is a revision to possibly show, in requested version
			$stableRevIds = $this->store->getStableRevisionIds( $page );
			$isRequestedStable = $forceStable || in_array( $selected->getId(), $stableRevIds );
			$hasStable = $stableRevIds !== [];
			$hasNewerStable = $hasStable && max( $stableRevIds ) > $selected->getId();
			$canSeeDrafts = !( $forUser instanceof UserIdentity ) || $this->canUserSeeUnstable( $forUser );

			$state = $isRequestedStable ? StableView::STATE_STABLE : StableView::STATE_UNSTABLE;
			if ( !$hasStable ) {
				// If it has no stable versions, it is first unstable
				$state = StableView::STATE_FIRST_UNSTABLE;
			}
			if ( $state === StableView::STATE_FIRST_UNSTABLE && !$canSeeDrafts && $this->isFirstUnstableAllowed() ) {
				// Exception for first drafts
				$canSeeDrafts = true;
			}
			if ( $state !== StableView::STATE_STABLE && !$canSeeDrafts ) {
				// Page is a draft, but user cannot see it, so we show the last stable version
				if ( $showingOld ) {
					// unless specific old revision is requested, show nothing if user cannot see that revision
					$this->stableViewCache[$cacheKey] = new StableView(
						null, $forUser, [], null, StableView::STATE_UNSTABLE, false, []
					);
					return $this->stableViewCache[$cacheKey];
				}
				$selected = $lastStable ? $lastStable->getRevision() : null;
				$isRequestedStable = true;
				$state = StableView::STATE_STABLE;
			}

			if ( !$selected ) {
				// Nothing to show
				$this->stableViewCache[$cacheKey] = new StableView(
					null, $forUser, [], null, StableView::STATE_UNSTABLE, false, []
				);
				return $this->stableViewCache[$cacheKey];
			}

			if ( isset( $options['transclusionCheck'] ) && $options['transclusionCheck'] ) {
				$this->stableViewCache[$cacheKey] = new StableView( $selected, null, [], null, 'undefined', false, [] );
				return $this->stableViewCache[$cacheKey];
			}

			// If is it the latest stable version, check if inclusions are in sync with current
			$outOfSyncInclusions = [];
			if ( !( $isRequestedStable && !$hasNewerStable ) ) {
				$inSync = true;
			} else {
				$outOfSyncInclusions = $this->inclusionManager->getSyncDifference( $lastStable );
				$inSync = empty( $outOfSyncInclusions );
			}

			// If viewing the latest stable, but it's not forced, and it's not in sync, set correct state
			if ( $isRequestedStable && !$forceStable && !$hasNewerStable && !$inSync && $canSeeDrafts ) {
				$state = StableView::STATE_IMPLICIT_UNSTABLE;
			}
			// If viewing an old revision, on the page which does not have later stable points, page needs stabilization
			$hasApprovableDraft =
				!$isRequestedStable ||
				( $canSeeDrafts && !$selected->isCurrent() && !$hasNewerStable );

			// Now we know we have page is either stable, or a draft that user can see
			// Show either stabilized inclusions, if stable is requested, or current inclusions if it is a draft
			if ( $state === StableView::STATE_STABLE ) {
				$inclusionsToShow = $lastStable->getInclusions();
			} else {
				$inclusionsToShow = $this->inclusionManager->getCurrentStabilizedInclusions( $selected );
			}

			$this->stableViewCache[$cacheKey] = new StableView(
				$selected, $forUser, $inclusionsToShow, $lastStable,
				$state, !$inSync || $hasApprovableDraft, $outOfSyncInclusions
			);
		}

		return $this->stableViewCache[$cacheKey];
	}

	/**
	 * @param UserIdentity $user
	 *
	 * @return bool
	 */
	public function canUserSeeUnstable( UserIdentity $user ): bool {
		$bot = new StabilizationBot();
		if ( $user->getName() === $bot->getUser()->getName() ) {
			// Bot can always see unstable
			return true;
		}
		$draftGroups = $this->config->get( 'DraftGroups' ) ?? [];
		// Sysop hardcoded
		$draftGroups[] = 'sysop';
		if ( in_array( '*', $draftGroups ) ) {
			return true;
		}
		if ( in_array( 'user', $draftGroups ) && $user->isRegistered() ) {
			return true;
		}
		return !empty( array_intersect(
			$this->userGroupManager->getUserEffectiveGroups( $user ),
			$draftGroups
		) );
	}

	/**
	 * @param PageIdentity|null $page
	 *
	 * @return bool
	 */
	public function isStabilizationEnabled( ?PageIdentity $page ): bool {
		if ( !$page || !$page->canExist() ) {
			return false;
		}
		$namespace = $page->getNamespace();
		if ( $namespace === NS_MEDIA ) {
			$namespace = NS_FILE;
		}

		$unavailable = $this->config->get( 'UnavailableNamespaces' );
		if ( in_array( $namespace, $unavailable ) ) {
			return false;
		}

		$result = in_array( $page->getNamespace(), $this->config->get( 'EnabledNamespaces' ) );
		if ( $namespace !== NS_FILE ) {
			$objectCache = $this->objectCacheFactory->getLocalServerInstance();

			$checkResult = $objectCache->getWithSetCallback(
				$objectCache->makeKey( 'contentstabilization-isstabilizationenabled', $page->getId() ),
				$objectCache::TTL_SECOND,
				function () use ( $page ) {
					$rev = $this->revisionStore->getRevisionByPageId( $page->getId() );
					if ( !$rev ) {
						return false;
					} elseif ( !( $rev->getContent( SlotRecord::MAIN ) instanceof WikitextContent ) ) {
						return false;
					}
				}
			);

			if ( $checkResult === false ) {
				$result = false;
			}
		}
		$this->hookContainer->run( 'ContentStabilizationIsStabilizationEnabled', [ $page, &$result ] );

		return $result;
	}

	/**
	 * @param PageIdentity $page
	 *
	 * @return bool
	 */
	public function hasStable( PageIdentity $page ): bool {
		return !empty( $this->store->getStableRevisionIds( $page ) );
	}

	/**
	 * @param File $file
	 *
	 * @return StablePoint|null
	 */
	public function getStablePointForFile( File $file ): ?StablePoint {
		$conditions = [ 'sp_page' => $file->getTitle()->getArticleID(), 'sfp_file_timestamp' => $file->getTimestamp() ];
		return $this->store->getLatestMatchingPoint( $conditions );
	}

	/**
	 * If suitable, add an upper-limit for revision ID
	 *
	 * @param array &$conds
	 * @param int|RevisionRecord|null $upToRevision
	 *
	 * @return void
	 */
	private function addUpToRevisionCondition( array &$conds, $upToRevision ) {
		if ( $upToRevision instanceof RevisionRecord ) {
			$conds[] = 'sp_revision <= ' . $upToRevision->getId();
		}
		if ( is_int( $upToRevision ) ) {
			$conds[] = 'sp_revision <= ' . $upToRevision;
		}
	}

	/**
	 * Return revision provided or latest revision of the page
	 *
	 * @param PageIdentity $page
	 * @param RevisionRecord|int|null $provided
	 *
	 * @return RevisionRecord|null
	 */
	private function providedRevisionOrLatest( PageIdentity $page, $provided ): ?RevisionRecord {
		if ( $provided instanceof RevisionRecord ) {
			return $provided;
		}
		if ( is_int( $provided ) ) {
			return $this->revisionStore->getRevisionById( $provided );
		}
		return $this->revisionStore->getRevisionByTitle( $page );
	}

	/**
	 * Complete initialization of the stable point by adding inclusions
	 *
	 * @param StablePoint|null $rawPoint
	 *
	 * @return StablePoint|null
	 */
	private function decorateWithInclusions( ?StablePoint $rawPoint ): ?StablePoint {
		if ( !$rawPoint ) {
			return null;
		}
		$inclusions = $this->inclusionManager->getStableInclusions( $rawPoint->getRevision() );
		$rawPoint->setInclusions( $inclusions );
		return $rawPoint;
	}

	/**
	 * @param int $namespace
	 *
	 * @return bool
	 */
	public function isStabilizedNamespace( int $namespace ) {
		return in_array( $namespace, $this->config->get( 'EnabledNamespaces' ) );
	}

	/**
	 * @param PageIdentity $page
	 *
	 * @return LinkTarget
	 */
	private function linkTargetFromPageIdentity( PageIdentity $page ): LinkTarget {
		return Title::castFromPageIdentity( $page );
	}
}
