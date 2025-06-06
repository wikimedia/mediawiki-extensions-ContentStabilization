<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\ReadConfirmation;

use BlueSpice\PageAssignments\AssignmentFactory;
use BlueSpice\ReadConfirmation\Event\ConfirmationRemindEvent;
use BlueSpice\ReadConfirmation\Event\ConfirmationRequestEvent;
use BlueSpice\ReadConfirmation\IMechanism;
use DateInterval;
use DateTime;
use Exception;
use MediaWiki\Config\Config;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MWStake\MediaWiki\Component\Events\Notifier;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Logic overview
 *
 * D = major draft, d = minor draft, S = major stable, s = minor stable, +r = read confirmed
 *
 * S+r -> d -> s => no read confirmation required
 * S+r -> D -> s => read confirmation required
 * S [-> d/D] -> s => read confirmation required
 * S+r [-> d/D] -> S => read confirmation required
 *
 * Class PageApproved
 * @package BlueSpice\FlaggedRevsConnector\ReadConfirmation\Mechanism
 */
class PageApproved implements IMechanism {

	/** @var ILoadBalancer */
	private $dbLoadBalancer;
	/** @var Config */
	private $config;
	/** @var LoggerInterface */
	private $logger;
	/** @var null */
	private $revisionId = null;
	/** @var RevisionLookup */
	private $revisionLookup;
	/** @var array */
	private $recentMustReadRevisions = [];
	/** @var AssignmentFactory */
	protected $assignmentFactory;
	/** @var Notifier */
	private $notifier;
	/** @var TitleFactory */
	private $titleFactory;
	/** @var StabilizationLookup */
	private $stabilizationLookup;

	/**
	 * @return PageApproved
	 */
	public static function factory() {
		$services = MediaWikiServices::getInstance();
		$config = $services->getMainConfig();

		return new self(
			$services->getDBLoadBalancer(),
			$config,
			$services->getRevisionLookup(),
			LoggerFactory::getInstance( 'stabilization' ),
			$services->getService( 'BSPageAssignmentsAssignmentFactory' ),
			$services->getTitleFactory(),
			$services->getService( 'ContentStabilization.Lookup' ),
			$services->getService( 'MWStake.Notifier' ),
			$services->getUserFactory()
		);
	}

	/**
	 * PageApproved constructor.
	 *
	 * @param ILoadBalancer $dbLoadBalancer
	 * @param Config $config
	 * @param RevisionLookup $revisionLookup
	 * @param LoggerInterface $logger
	 * @param AssignmentFactory $assignmentFactory
	 * @param TitleFactory $titleFactory
	 * @param StabilizationLookup $stabilizationLookup
	 * @param Notifier $notifier
	 */
	protected function __construct(
		ILoadBalancer $dbLoadBalancer,
		Config $config,
		RevisionLookup $revisionLookup,
		LoggerInterface $logger,
		AssignmentFactory $assignmentFactory,
		TitleFactory $titleFactory,
		StabilizationLookup $stabilizationLookup,
		Notifier $notifier,
		private readonly UserFactory $userFactory
	) {
		$this->dbLoadBalancer = $dbLoadBalancer;
		$this->config = $config;
		$this->revisionLookup = $revisionLookup;
		$this->logger = $logger;
		$this->assignmentFactory = $assignmentFactory;
		$this->notifier = $notifier;
		$this->titleFactory = $titleFactory;
		$this->stabilizationLookup = $stabilizationLookup;
	}

	/**
	 * @return void
	 */
	public function wireUpNotificationTrigger() {
	}

	/**
	 * @param Title $title
	 * @param User $userAgent
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function notifyDaily( Title $title, User $userAgent ) {
		if ( !$title->exists() ) {
			return false;
		}
		if ( $this->isMinorRevision( $title->getArticleID() )
			&& $this->hasNoPreviousMajorRevisionDrafts( $title->getArticleID() ) ) {
			return false;
		}
		$notifyUsers = $this->getNotifyUsers( $title->getArticleID() );
		$event = new ConfirmationRemindEvent( $title, $notifyUsers );
		$this->notifier->emit( $event );
		return true;
	}

	/**
	 * @param Title $title
	 * @param User $userAgent
	 *
	 * @return bool|array
	 * @throws Exception
	 */
	public function notify( Title $title, User $userAgent ) {
		if ( !$title->exists() ) {
			return false;
		}
		if ( $this->isMinorRevision( $title->getArticleID() )
			&& $this->hasNoPreviousMajorRevisionDrafts( $title->getArticleID() ) ) {
			return false;
		}
		$notifyUsers = $this->getNotifyUsers( $title->getArticleID() );
		$event = new ConfirmationRequestEvent( $userAgent, $title, $notifyUsers );
		$this->notifier->emit( $event );
		return $notifyUsers;
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function autoNotify() {
		$delay = $this->config->get( 'BlueSpicePageApprovedReminderDelay' );
		$now = new DateTime();
		$now->setTime( 0, 0, 0 );
		$now->sub( new DateInterval( "PT{$delay}H" ) );

		$db = $this->dbLoadBalancer->getConnection( DB_REPLICA );
		$res = $db->select(
			'stable_points',
			[ 'sp_page' ],
			"sp_time < " . $db->addQuotes( $now->format( 'YmdHis' ) ),
			__METHOD__,
			[
				'DISTINCT' => true
			]
		);

		if ( $res->numRows() > 0 ) {
			return;
		}
		$agent = User::newSystemUser( 'Mediawiki default', [ 'steal' => true ] );
		foreach ( $res as $row ) {
			$title = $this->titleFactory->newFromID( $row->sp_page );
			if ( !$title ) {
				continue;
			}
			$this->notifyDaily( $title, $agent );
		}
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param int|null $revId
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function canConfirm( Title $title, User $user, $revId = null ) {
		if ( !$this->stabilizationLookup->isStabilizationEnabled( $title ) ) {
			return false;
		}
		if ( !in_array( $user->getId(), $this->getAssignedUsers( $title->getArticleID() ) ) ) {
			return false;
		}

		$stable = $this->stabilizationLookup->getLastStableRevision( $title );
		if ( !( $stable instanceof RevisionRecord ) ) {
			// first draft
			return false;
		}
		if ( $revId && $revId !== $stable->getId() ) {
			return false;
		}
		$revId = $stable->getId();

		if ( $this->isMinorRevision( $revId ) ) {
			if ( $this->hasNoPreviousMajorRevisionDrafts( $revId ) ) {
				return false;
			}
			$this->logger->debug(
				'Requested rev_id = {revId} is minor',
				[
					'revId' => $revId
				]
			);
			$revId = $this->getRecentMustReadRevision( $title->getArticleID() );
		}

		$arrayWithThisUsersIdIfAlreadyReadTheRevision =
			$this->usersAlreadyReadRevision( $revId, [ $user->getId() ] );
		if ( !empty( $arrayWithThisUsersIdIfAlreadyReadTheRevision ) ) {
			return false;
		}

		$this->revisionId = $revId;
		return true;
	}

	/**
	 *
	 * @param int $revId
	 * @return bool
	 */
	private function isMinorRevision( $revId ) {
		if ( !$this->includeMinor() ) {
			return false;
		}
		$revision = $this->revisionLookup->getRevisionById( $revId );
		if ( $revision instanceof RevisionRecord ) {
			return $revision->isMinor();
		}

		return false;
	}

	/**
	 *
	 * @param int $revId
	 * @return bool
	 */
	private function hasNoPreviousMajorRevisionDrafts( $revId ) {
		$revision = $this->revisionLookup->getRevisionById( $revId );
		if ( $revision instanceof RevisionRecord ) {
			$previousRevision = $this->revisionLookup->getPreviousRevision( $revision );
			if ( $this->stabilizationLookup->isStableRevision( $previousRevision ) ) {
				return true;
			}
			while ( $previousRevision instanceof RevisionRecord ) {
				if ( !$previousRevision->isMinor() ) {
					return false;
				}
				$previousRevision = $this->revisionLookup->getPreviousRevision( $previousRevision );
			}
		}
		return true;
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param int|null $revId
	 * @return bool
	 */
	public function confirm( Title $title, User $user, $revId = null ) {
		if ( !$this->canConfirm( $title, $user, $revId ) ) {
			return false;
		}

		$this->logger->debug(
			'Read confirmation, requested rev_id = {revId}, final rev_id = {fRevId}, user_id = {userId}',
			[
				'revId' => $revId,
				'fRevId' => $this->revisionId,
				'userId' => $user->getId()
			]
		);

		$row = [
			'rc_rev_id' => $this->revisionId,
			'rc_user_id' => $user->getId(),
			'rc_timestamp' => wfTimestampNow()
		];

		$this->dbLoadBalancer->getConnection( DB_PRIMARY )->upsert(
			'bs_readconfirmation',
			$row,
			[ [ 'rc_rev_id', 'rc_user_id' ] ],
			$row,
			__METHOD__
		);

		return true;
	}

	/**
	 * @param Title $title
	 * @return bool
	 */
	public function mustRead( Title $title ) {
		$enabledNamespaces =
			$this->config->has( 'NamespacesWithEnabledReadConfirmation' )
			? $this->config->get( 'NamespacesWithEnabledReadConfirmation' )
			: [];
		if (
			!isset( $enabledNamespaces[$title->getNamespace()] ) ||
			!$enabledNamespaces[$title->getNamespace()]
		) {
			return false;
		}

		$revPending = $this->getRecentMustReadRevision( $title->getArticleID() );
		if ( !$revPending ) {
			return false;
		}

		return true;
	}

	/**
	 * @param int $pageId
	 * @return bool
	 */
	protected function getRecentMustReadRevision( $pageId ) {
		if ( !isset( $this->recentMustReadRevisions[$pageId] ) ) {
			$this->recentMustReadRevisions[$pageId] = false;
			$mustReadRevision = $this->getMustReadRevisions( [ $pageId ] );
			if ( isset( $mustReadRevision[$pageId] ) ) {
				$this->recentMustReadRevisions[$pageId] = $mustReadRevision[$pageId];
			}
		}
		return $this->recentMustReadRevisions[$pageId];
	}

	/**
	 * @param int $pageId
	 *
	 * @return array
	 * @throws Exception
	 */
	private function getNotifyUsers( $pageId ) {
		$affectedUsers = $this->getAssignedUsers( $pageId );
		if ( empty( $affectedUsers ) ) {
			return [];
		}
		$revId = $this->getRecentMustReadRevision( $pageId );
		if ( !$revId ) {
			return [];
		}
		$ids = array_diff(
			$affectedUsers,
			$this->usersAlreadyReadRevision( $revId, $affectedUsers )
		);

		$notReadUsers = array_map( function ( $id ) {
			return $this->userFactory->newFromId( $id );
		}, $ids );

		return array_filter( $notReadUsers );
	}

	/**
	 * @param int $pageId
	 *
	 * @return array
	 * @throws Exception
	 */
	private function getAssignedUsers( $pageId ) {
		$title = $this->titleFactory->newFromID( $pageId );
		if ( !$title || !$title->exists() ) {
			return [];
		}
		$target = $this->assignmentFactory->newFromTargetTitle( $title );
		if ( !$target ) {
			return [];
		}
		return $target->getAssignedUserIDs();
	}

	/**
	 * @param int $revId
	 * @return bool
	 */
	private function isRevisionStable( $revId ) {
		$revision = $this->revisionLookup->getRevisionById( $revId );
		if ( !$revision ) {
			return false;
		}
		$stablePoint = $this->stabilizationLookup->getStablePointForRevision( $revision );
		return $stablePoint instanceof StablePoint;
	}

	/**
	 * Gets THE LATEST read revisions of each page for each user specified
	 *
	 * @param array $userIds List of user IDs
	 * @return array Array with such structure:
	 *  [
	 *    <user_id1> => [
	 *		 <page_id1> => <latest_read_revision1>,
	 * 		 <page_id2> => <latest_read_revision2>
	 *	  ],
	 * 	  <user_id2> => [
	 * 	  ...
	 * 	  ],
	 * 	  ...
	 * 	]
	 */
	private function getUserLatestReadRevisions( array $userIds ): array {
		if ( !$userIds ) {
			return [];
		}

		$res = $this->dbLoadBalancer->getConnection( DB_REPLICA )->select(
			[
				'revision',
				'bs_readconfirmation'
			],
			[
				'latest_rev' => 'MAX(rc_rev_id)',
				'rev_page',
				'rc_user_id'
			],
			[ 'rc_user_id' => $userIds ],
			__METHOD__,
			[
				'GROUP BY' => [
					'rev_page',
					'rc_user_id'
				]
			],
			[
				'bs_readconfirmation' => [
					'INNER JOIN', 'rc_rev_id = rev_id'
				]
			]
		);

		$latestRevs = [];
		foreach ( $res as $row ) {
			$latestRevs[ (int)$row->rc_user_id ][ (int)$row->rev_page ] = (int)$row->latest_rev;
		}
		return $latestRevs;
	}

	/**
	 * @inheritDoc
	 */
	public function getLatestReadConfirmations( array $userIds = [] ): array {
		$userLatestReadRevisions = $this->getUserLatestReadRevisions( $userIds );

		$pageIds = [];
		foreach ( $userLatestReadRevisions as $latestReadRevisionData ) {
			foreach ( $latestReadRevisionData as $pageId => $latestReadRevisionId ) {
				$pageIds[$pageId] = true;
			}
		}
		$pageIds = array_keys( $pageIds );

		$recentRevisions = $this->getMustReadRevisions( $pageIds );

		$readConfirmations = [];
		foreach ( $userLatestReadRevisions as $userId => $latestReadRevisionData ) {
			foreach ( $latestReadRevisionData as $pageId => $latestReadRevisionId ) {
				// In case if there are no major revisions of the page
				$recentRevisionId = 0;

				// There is some major revision of the page
				if ( isset( $recentRevisions[ $pageId ] ) ) {
					$recentRevisionId = $recentRevisions[ $pageId ];
				}

				$readConfirmations[$userId][$pageId] = [
					'latest_rev' => $recentRevisionId,
					'latest_read_rev' => $latestReadRevisionId
				];
			}
		}

		return $readConfirmations;
	}

	/**
	 * @param array $userIds
	 * @param array $pageIds
	 * @return array [ <page_id> => [ <user_id1>, <user_id2>, ...], ... ]
	 */
	public function getCurrentReadConfirmations( array $userIds = [], array $pageIds = [] ) {
		$currentReadConfirmations = [];
		$userReadRevisions = $this->getUserReadRevisions( $userIds );
		$recentRevisions = $this->getMustReadRevisions( $pageIds );
		foreach ( $pageIds as $pageId ) {
			$reads = [];
			if (
				isset( $recentRevisions[$pageId] ) &&
				isset( $userReadRevisions[$recentRevisions[$pageId]] )
			) {
				$reads = $userReadRevisions[$recentRevisions[$pageId]];
			}
			$currentReadConfirmations[$pageId] = $reads;
		}

		return $currentReadConfirmations;
	}

	/**
	 * @param \MediaWiki\Title\Title $title
	 * @param \MediaWiki\User\User $user
	 * @return RevisionRecord|null
	 */
	public function getLatestRevisionToConfirm(
		\MediaWiki\Title\Title $title, \MediaWiki\User\User $user
	): ?RevisionRecord {
		$latestStable = $this->stabilizationLookup->getLastStablePoint( $title );
		if ( !$latestStable ) {
			return null;
		}
		return $latestStable->getRevision();
	}

	/**
	 * @param int $revisionId
	 * @param array $userIds
	 * @return array
	 */
	private function usersAlreadyReadRevision( int $revisionId, array $userIds ) {
		$res = $this->dbLoadBalancer->getConnection( DB_REPLICA )->select(
			'bs_readconfirmation',
			'*',
			[
				'rc_user_id' => $userIds,
				'rc_rev_id' => $revisionId
			],
			__METHOD__
		);

		if ( $res->numRows() > 0 ) {
			$userIds = [];
			foreach ( $res as $row ) {
				$userIds[] = $row->rc_user_id;
			}
			return $userIds;
		}

		return [];
	}

	/**
	 * @param array $userIds
	 * @return array
	 */
	private function getUserReadRevisions( $userIds = [] ) {
		if ( !$userIds ) {
			return [];
		}
		$res = $this->dbLoadBalancer
			->getConnection( DB_REPLICA )
			->select(
				'bs_readconfirmation',
				'*',
				[ 'rc_user_id' => $userIds ],
				__METHOD__
			);

		$readRevisions = [];
		foreach ( $res as $row ) {
			$revId = (int)$row->rc_rev_id;
			if ( !isset( $readRevisions[ $revId ] ) ) {
				$readRevisions[ $revId ] = [];
			}
			$readRevisions[ $revId ][(int)$row->rc_user_id] = $row->rc_timestamp;
		}

		return $readRevisions;
	}

	/**
	 * @param array $pageIds
	 * @return array
	 */
	private function getMustReadRevisions( array $pageIds = [] ) {
		if ( !$pageIds ) {
			return [];
		}
		$recentData = [];

		$sqb = $this->dbLoadBalancer->getConnection( DB_REPLICA )->newSelectQueryBuilder();
		$sqb
			->from( 'revision', 'r' )
			->select( [ 'MAX( r.rev_id ) as chosen_rev', 'r.rev_page' ] )
			->join(
				$sqb->newSubquery()->select( [ 'sp_page', 'MAX(sp_revision) AS last_stable' ] )
					->from( 'stable_points' )
					->groupBy( 'sp_page' ),
				'sp',
				'r.rev_page = sp.sp_page'
			)
			->where( 'r.rev_id <= sp.last_stable' )
			->where( [ 'r.rev_page' => $pageIds ] )
			->groupBy( 'r.rev_page' );
		if ( $this->includeMinor() ) {
			$sqb->where( 'r.rev_minor_edit = 0' );
		}
		$res = $sqb->fetchResultSet();
		foreach ( $res as $row ) {
			if ( isset( $recentData[$row->rev_page] ) ) {
				continue;
			}
			$recentData[$row->rev_page] = (int)$row->rev_id;
		}

		return $recentData;
	}

	/**
	 * Should trigger RC on minor stable revisions
	 * @return bool
	 */
	protected function includeMinor(): bool {
		return true;
	}

}
