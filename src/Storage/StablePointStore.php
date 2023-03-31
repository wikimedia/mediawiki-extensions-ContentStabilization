<?php

namespace MediaWiki\Extension\ContentStabilization\Storage;

use DateTime;
use Exception;
use File;
use MediaWiki\Extension\ContentStabilization\StableFilePoint;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserFactory;
use RepoGroup;
use ResultWrapper;
use stdClass;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @private to ContentStabilization
 */
class StablePointStore {
	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var UserFactory */
	private $userFactory;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var RepoGroup */
	private $repoGroup;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param UserFactory $userFactory
	 * @param RevisionStore $revisionStore
	 * @param RepoGroup $repoGroup
	 */
	public function __construct(
		ILoadBalancer $loadBalancer, UserFactory $userFactory,
		RevisionStore $revisionStore, RepoGroup $repoGroup
	) {
		$this->loadBalancer = $loadBalancer;
		$this->userFactory = $userFactory;
		$this->revisionStore = $revisionStore;
		$this->repoGroup = $repoGroup;
	}

	/**
	 * @param array|null $conds
	 *
	 * @return StablePoint[]
	 */
	public function query( ?array $conds = [] ): array {
		$res = $this->rawQuery( $conds );

		$points = [];
		foreach ( $res as $row ) {
			$points[] = $this->stablePointFromRow( $row );
		}
		return array_filter( $points );
	}

	/**
	 * @param PageIdentity $page
	 *
	 * @return array
	 */
	public function getStableRevisionIds( PageIdentity $page ): array {
		$db = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		$res = $db->select(
			'stable_points',
			[ 'sp_revision' ],
			[ 'sp_page' => $page->getId() ],
			__METHOD__,
			[ 'ORDER BY' => 'sp_revision ASC' ]
		);

		$revisions = [];
		foreach ( $res as $row ) {
			$revisions[] = (int)$row->sp_revision;
		}
		return $revisions;
	}

	/**
	 * @param array|null $conds
	 *
	 * @return StablePoint|null
	 */
	public function getLatestMatchingPoint( $conds = [] ): ?StablePoint {
		$res = $this->rawQuery( $conds );
		$row = $res->fetchRow();
		if ( !$row ) {
			return null;
		}
		return $this->stablePointFromRow( (object)$row );
	}

	/**
	 * @param stdClass $row
	 *
	 * @return StablePoint
	 */
	private function stablePointFromRow( $row ): StablePoint {
		$revision = $this->revisionStore->getRevisionById( $row->sp_revision );
		$actor = $this->userFactory->newFromId( $row->sp_user );
		$time = DateTime::createFromFormat( 'YmdHis', $row->sp_time );
		$file = $this->maybeGetFile( $revision, $row );
		if ( $file ) {
			return new StableFilePoint( $file, $revision, $actor, $time, $row->sp_comment );
		}
		return new StablePoint( $revision, $actor, $time, $row->sp_comment ?? '' );
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 * @param Authority $approver
	 * @param string $comment
	 *
	 * @return void
	 */
	public function insertStablePoint( RevisionRecord $revisionRecord, Authority $approver, string $comment ) {
		$db = $this->loadBalancer->getConnectionRef( DB_PRIMARY );
		$res = $db->insert(
			'stable_points',
			[
				'sp_page' => $revisionRecord->getPage()->getId(),
				'sp_revision' => $revisionRecord->getId(),
				'sp_time' => $db->timestamp(),
				'sp_user' => $approver->getUser()->getId(),
				'sp_comment' => $comment,
			],
			__METHOD__
		);
		$this->maybeInsertFileStablePoint( $revisionRecord );

		if ( !$res ) {
			throw new \RuntimeException( 'Failed to insert stable point' );
		}
	}

	/**
	 * @param StablePoint $point
	 *
	 * @return void
	 */
	public function removeStablePoint( StablePoint $point ) {
		$db = $this->loadBalancer->getConnectionRef( DB_PRIMARY );
		$res = $db->delete(
			'stable_points',
			[
				'sp_page' => $point->getRevision()->getPage()->getId(),
				'sp_revision' => $point->getRevision()->getId(),
			],
			__METHOD__
		);
		$res2 = $db->delete(
			'stable_file_points',
			[ 'sfp_revision' => $point->getRevision()->getId() ],
			__METHOD__
		);

		if ( !$res || !$res2 ) {
			throw new \RuntimeException( 'Failed to remove stable point' );
		}
	}

	/**
	 * @param StablePoint $point
	 * @param RevisionRecord $revisionRecord
	 * @param Authority $approver
	 * @param string $comment
	 *
	 * @return void
	 */
	public function updateStablePoint(
		StablePoint $point, RevisionRecord $revisionRecord, Authority $approver, string $comment
	) {
		$db = $this->loadBalancer->getConnectionRef( DB_PRIMARY );
		$res = $db->update(
			'stable_points',
			[
				'sp_revision' => $revisionRecord->getId(),
				'sp_user' => $approver->getUser()->getId(),
				'sp_time' => $db->timestamp(),
				'sp_comment' => $comment,
			],
			[
				'sp_page' => $point->getRevision()->getPage()->getId(),
				'sp_revision' => $point->getRevision()->getId(),
			],
			__METHOD__
		);
		$this->maybeUpdateFileStablePoint( $revisionRecord );

		if ( !$res ) {
			throw new \RuntimeException( 'Failed to update stable point' );
		}
	}

	/**
	 * @param PageIdentity $page
	 *
	 * @return void
	 */
	public function removeStablePointsForPage( PageIdentity $page ) {
		$db = $this->loadBalancer->getConnectionRef( DB_PRIMARY );
		$res = $db->delete(
			'stable_points',
			[
				'sp_page' => $page->getId(),
			],
			__METHOD__
		);
		$db->delete(
			'stable_file_points',
			[
				'sfp_page' => $page->getId(),
			],
			__METHOD__
		);

		if ( !$res ) {
			throw new \RuntimeException( 'Failed to remove stable points for page' );
		}
	}

	/**
	 * @param array|null $conds
	 *
	 * @return ResultWrapper
	 */
	private function rawQuery( ?array $conds ): ResultWrapper {
		$db = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		return $db->select(
			[ 'stable_points', 'stable_file_points' ],
			[ 'sp_page', 'sp_revision', 'sp_time', 'sp_user', 'sp_comment', 'sfp_file_timestamp', 'sfp_file_sha1' ],
			$conds,
			__METHOD__,
			[ 'ORDER BY' => 'sp_revision DESC' ],
			[ 'stable_file_points' => [ 'LEFT JOIN', 'sfp_revision = sp_revision' ] ],
		);
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return bool
	 */
	private function maybeInsertFileStablePoint( RevisionRecord $revisionRecord ): bool {
		if ( $revisionRecord->getPage()->getNamespace() !== NS_FILE ) {
			return true;
		}
		$file = $this->repoGroup->getLocalRepo()->findFile( $revisionRecord->getPage() );
		if ( !$file ) {
			return false;
		}
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		return $db->insert(
			'stable_file_points',
			[
				'sfp_revision' => $revisionRecord->getId(),
				'sfp_page' => $revisionRecord->getPage()->getId(),
				'sfp_file_timestamp' => $file->getTimestamp(),
				'sfp_file_sha1' => $file->getSha1()
			],
			__METHOD__
		);
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return bool
	 */
	private function maybeUpdateFileStablePoint( RevisionRecord $revisionRecord ): bool {
		if ( $revisionRecord->getPage()->getNamespace() !== NS_FILE ) {
			return true;
		}
		$file = $this->repoGroup->getLocalRepo()->findFile( $revisionRecord->getPage() );
		if ( !$file ) {
			return false;
		}
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		return $db->update(
			'stable_file_points',
			[
				'sfp_file_timestamp' => $file->getTimestamp(),
				'sfp_file_sha1' => $file->getSha1()
			],
			[
				'sfp_page' => $revisionRecord->getPage()->getId(),
				'sfp_revision' => $revisionRecord->getId(),
			],
			__METHOD__
		);
	}

	/**
	 * Try to retrieve the file for the given stable point.
	 *
	 * @param RevisionRecord $revisionRecord
	 * @param stdClass $row
	 *
	 * @return File|null if not applicable
	 * @throws Exception if applicable, but file cannot be found
	 */
	private function maybeGetFile( RevisionRecord $revisionRecord, stdClass $row ): ?File {
		if ( !property_exists( $row, 'sfp_file_timestamp' ) || !$row->sfp_file_timestamp ) {
			return null;
		}
		$file = $this->repoGroup->getLocalRepo()->findFile( $revisionRecord->getPage(), [
			'time' => $row->sfp_file_timestamp,
		] );
		if ( !$file ) {
			throw new Exception( 'Invalid stable file point' );
		}
		return $file;
	}
}
