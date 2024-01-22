<?php

namespace MediaWiki\Extension\ContentStabilization;

use InvalidArgumentException;
use MediaWiki\Extension\ContentStabilization\Storage\StablePointStore;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;

final class ContentStabilizer {
	/** @var StablePointStore */
	private $store;

	/** @var StabilizationLookup */
	private $lookup;

	/** @var InclusionManager */
	private $inclusionManager;

	/** @var PermissionManager */
	private $permissionManager;
	/** @var HookContainer */
	private $hookContainer;

	/**
	 * @param StablePointStore $store
	 * @param StabilizationLookup $lookup
	 * @param InclusionManager $inclusionManager
	 * @param PermissionManager $permissionManager
	 * @param HookContainer $hookContainer
	 */
	public function __construct(
		StablePointStore $store, StabilizationLookup $lookup, InclusionManager $inclusionManager,
		PermissionManager $permissionManager, HookContainer $hookContainer
	) {
		$this->store = $store;
		$this->lookup = $lookup;
		$this->inclusionManager = $inclusionManager;
		$this->permissionManager = $permissionManager;
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 * @param Authority $approver
	 * @param string $comment
	 *
	 * @return StablePoint|null
	 */
	public function addStablePoint(
		RevisionRecord $revisionRecord, Authority $approver, string $comment
	): ?StablePoint {
		$this->assertEligible( $revisionRecord );
		$this->assertCurrent( $revisionRecord );
		$this->assertNotStable( $revisionRecord );
		$this->assertUserIsAllowed(
			$approver, $revisionRecord->getPageAsLinkTarget(),
			'contentstabilization-stabilize'
		);

		$this->store->insertStablePoint( $revisionRecord, $approver, $comment );
		$this->inclusionManager->stabilizeInclusions( $revisionRecord );
		$this->store->clearCache();
		$newStable = $this->lookup->getStablePointForRevision( $revisionRecord );
		$this->hookContainer->run( 'ContentStabilizationStablePointAdded', [ $newStable ] );
		return $newStable;
	}

	/**
	 * @param StablePoint $point
	 * @param Authority $approver
	 * @param string $comment
	 *
	 * @return StablePoint|null
	 */
	public function updateStablePoint( StablePoint $point, Authority $approver, string $comment ) {
		$this->assertUserIsAllowed(
			$approver, $point->getRevision()->getPageAsLinkTarget(),
			'contentstabilization-stabilize'
		);
		$this->store->updateStablePoint( $point, $point->getRevision(), $approver, $comment );
		$this->inclusionManager->stabilizeInclusions( $point->getRevision() );
		$this->store->clearCache();
		$updated = $this->lookup->getStablePointForRevision( $point->getRevision() );
		$this->hookContainer->run( 'ContentStabilizationStablePointUpdated', [ $updated ] );
		return $updated;
	}

	/**
	 * @param StablePoint $point
	 * @param Authority $actor
	 *
	 * @return void
	 */
	public function removeStablePoint( StablePoint $point, Authority $actor ) {
		$this->assertUserIsAllowed(
			$actor, $point->getRevision()->getPageAsLinkTarget(),
			'contentstabilization-admin'
		);
		$this->inclusionManager->removeStableInclusionsForRevision( $point->getRevision() );
		$this->store->removeStablePoint( $point );
		$this->store->clearCache();
		$this->hookContainer->run( 'ContentStabilizationStablePointRemoved', [ $point, $actor ] );
	}

	/**
	 * @param StablePoint $point
	 * @param RevisionRecord $revisionRecord
	 * @param Authority $approver
	 * @param string $comment
	 *
	 * @return StablePoint
	 */
	public function moveStablePoint(
		StablePoint $point, RevisionRecord $revisionRecord, Authority $approver, string $comment
	): StablePoint {
		$this->assertUserIsAllowed(
			$approver, $point->getRevision()->getPageAsLinkTarget(),
			'contentstabilization-admin'
		);
		$this->assertNotStable( $revisionRecord );
		$this->assertRevisionsAreOnSamePage( $point->getRevision(), $revisionRecord );
		$this->assertNoStablePointBetween( $point->getRevision(), $revisionRecord );

		$this->inclusionManager->removeStableInclusionsForRevision( $point->getRevision() );
		$this->store->updateStablePoint( $point, $revisionRecord, $approver, $comment );
		$this->inclusionManager->stabilizeInclusions( $revisionRecord );
		$this->store->clearCache();
		$newPoint = $this->lookup->getStablePointForRevision( $revisionRecord );
		$this->hookContainer->run( 'ContentStabilizationStablePointMoved', [ $point, $newPoint ] );
		return $newPoint;
	}

	/**
	 * Bulk remove all stable points for a page (usually on page deletion or maybe on disabling stabilization)
	 *
	 * @param PageIdentity $page
	 *
	 * @return void
	 */
	public function removeStablePointsForPage( PageIdentity $page ) {
		$this->store->removeStablePointsForPage( $page );
	}

	/**
	 * @return InclusionManager
	 */
	public function getInclusionManager(): InclusionManager {
		return $this->inclusionManager;
	}

	/**
	 * @param PageIdentity $page
	 *
	 * @return bool
	 */
	public function isEligibleForStabilization( PageIdentity $page ): bool {
		// Convenience
		return $this->lookup->isStabilizationEnabled( $page );
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return void
	 */
	private function assertCurrent( RevisionRecord $revisionRecord ) {
		if ( !$revisionRecord->isCurrent() ) {
			throw new InvalidArgumentException( 'Cannot mark non-current revision as stable' );
		}
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return void
	 */
	private function assertNotStable( RevisionRecord $revisionRecord ) {
		$point = $this->store->getLatestMatchingPoint( [ 'sp_revision' => $revisionRecord->getId() ] );
		if ( $point instanceof StablePoint ) {
			throw new InvalidArgumentException( 'Revision is already registered as a stable point' );
		}
	}

	/**
	 * @param Authority $user
	 * @param LinkTarget $page
	 * @param string $permission
	 *
	 * @return void
	 */
	private function assertUserIsAllowed( Authority $user, LinkTarget $page, string $permission ) {
		if ( $user instanceof StabilizationBot ) {
			return;
		}
		if ( !$user->isRegistered() ) {
			throw new InvalidArgumentException( 'User must be registered' );
		}
		if ( $user->getBlock() ) {
			throw new InvalidArgumentException( 'User is blocked' );
		}
		if ( !$this->permissionManager->userCan( $permission, $user, $page ) ) {
			throw new InvalidArgumentException( 'User is not allowed to perform this action' );
		}
	}

	/**
	 * @param RevisionRecord $old
	 * @param RevisionRecord $new
	 *
	 * @return void
	 */
	private function assertNoStablePointBetween( RevisionRecord $old, RevisionRecord $new ) {
		$between = $this->store->query( [
			'sp_page' => $new->getPage()->getId(),
			'sp_revision > ' . $old->getId(),
			'sp_revision < ' . $new->getId()
		] );
		if ( !empty( $between ) ) {
			throw new InvalidArgumentException( 'Cannot move stable point over another stable point' );
		}
	}

	/**
	 * @param RevisionRecord $getRevision
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return void
	 */
	private function assertRevisionsAreOnSamePage( RevisionRecord $getRevision, RevisionRecord $revisionRecord ) {
		if ( $getRevision->getPage()->getId() !== $revisionRecord->getPage()->getId() ) {
			throw new InvalidArgumentException( 'Cannot move stable point to another page' );
		}
	}

	/**
	 *
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return void
	 */
	private function assertEligible( RevisionRecord $revisionRecord ) {
		if ( !$this->isEligibleForStabilization( $revisionRecord->getPage() ) ) {
			throw new InvalidArgumentException( 'Page is not eligible for stabilization' );
		}
	}
}
