<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Workflows\Activity;

use Exception;
use MediaWiki\Extension\ContentStabilization\ContentStabilizer;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Extension\Workflows\Activity\ExecutionStatus;
use MediaWiki\Extension\Workflows\Activity\GenericActivity;
use MediaWiki\Extension\Workflows\Definition\ITask;
use MediaWiki\Extension\Workflows\Exception\WorkflowExecutionException;
use MediaWiki\Extension\Workflows\WorkflowContext;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserFactory;
use Message;
use MWTimestamp;
use Title;
use TitleFactory;
use User;

/**
 * Required data:
 * - WorkflowDefinitionContext: pageId, revision
 * - Properties: comment (optional)
 * Outputs:
 * - timestamp
 */
class ApprovePageActivity extends GenericActivity {
	/** @var ContentStabilizer */
	private $stabilizer;
	/** @var StabilizationLookup */
	private $stabilizationLookup;
	/** @var RevisionStore */
	private $revisionStore;
	/** @var UserFactory */
	private $userFactory;
	/** @var Title */
	private $title;
	/** @var RevisionRecord */
	private $revision;
	/** @var User */
	private $maintenanceUser;
	/** @var User */
	private $user;
	/** @var TitleFactory */
	private $titleFactory;

	/**
	 *
	 * @param TitleFactory $titleFactory
	 * @param ContentStabilizer $stabilizer
	 * @param StabilizationLookup $stabilizationLookup
	 * @param RevisionStore $revisionStore
	 * @param UserFactory $userFactory
	 * @param ITask $task
	 */
	public function __construct(
		TitleFactory $titleFactory, ContentStabilizer $stabilizer, StabilizationLookup $stabilizationLookup,
		RevisionStore $revisionStore, UserFactory $userFactory, ITask $task
	) {
		parent::__construct( $task );
		$this->titleFactory = $titleFactory;
		$this->stabilizer = $stabilizer;
		$this->stabilizationLookup = $stabilizationLookup;
		$this->revisionStore = $revisionStore;
		$this->userFactory = $userFactory;
		$this->maintenanceUser = User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] );
	}

	/**
	 * @param array $data
	 * @param WorkflowContext $context
	 * @return ExecutionStatus
	 * @throws WorkflowExecutionException
	 */
	public function execute( $data, WorkflowContext $context ): ExecutionStatus {
		$this->setPageData( $context, $data );
		$this->assertApprovable();
		$comment = $data['comment'] ?? '';

		$this->doApprove( $comment );
		return new ExecutionStatus( static::STATUS_COMPLETE, [ 'timestamp' => MWTimestamp::now() ] );
	}

	/**
	 *
	 * @param WorkflowContext $context
	 * @param array $data
	 * @return void
	 * @throws WorkflowExecutionException
	 */
	private function setPageData( WorkflowContext $context, $data ) {
		if ( !$context->getDefinitionContext()->getItem( 'pageId' ) ) {
			throw new WorkflowExecutionException(
				Message::newFromKey(
					'content-stabilization-integration-activity-error-context-data-missing'
				)->text(),  $this->getTask()
			);
		}

		$title = $this->titleFactory->newFromID( $context->getDefinitionContext()->getItem( 'pageId' ) );
		$revisionId = $data['revision'] ?? $context->getDefinitionContext()->getItem( 'revision' );
		if ( !$revisionId ) {
			$revisionId = $title->getLatestRevID();
		}
		if ( !$title instanceof Title || !$title->exists() ) {
			throw new WorkflowExecutionException(
				Message::newFromKey(
					'content-stabilization-integration-activity-error-context-invalid-title'
				)->text(),  $this->getTask()
			);
		}
		$this->title = $title;

		$revision = $this->revisionStore->getRevisionById( (int)$revisionId );
		if ( $revision === null ) {
			$revision = $this->revisionStore->getRevisionById( $title->getLatestRevID() );
		}
		if ( $revision->getPageId() !== $title->getArticleID() ) {
			throw new WorkflowExecutionException(
				Message::newFromKey(
					'content-stabilization-integration-activity-error-title-rev-mismatch'
				)->text(),  $this->getTask()
			);
		}
		$this->revision = $revision;

		if ( isset( $data['user'] ) ) {
			// If user is explicitly set, use that. Definition is responsible
			// to make sure this user can approve pages (use propertyValidator)
			$this->user = $this->userFactory->newFromName( $data['user'] );
			if ( !( $this->user instanceof User ) || !$this->user->isRegistered() ) {
				throw new WorkflowExecutionException(
					Message::newFromKey(
						'content-stabilization-integration-activity-error-provided-user', $data['user']
					)->text(),  $this->getTask()
				);
			}
		} elseif ( $context->isRunningAsBot() ) {
			// If we are running a workflow as a bot (no user interaction), use maintenance user
			$this->user = $this->maintenanceUser;
		}

		if ( !( $this->user instanceof User ) ) {
			throw new WorkflowExecutionException(
				Message::newFromKey(
					'content-stabilization-integration-activity-error-no-user'
				)->text(),  $this->getTask()
			);
		}
	}

	/**
	 *
	 * @return bool
	 */
	private function assertApprovable() {
		return $this->stabilizer->isEligibleForStabilization( $this->title->toPageIdentity() );
	}

	/**
	 *
	 * @param string $comment
	 *
	 * @return void
	 * @throws WorkflowExecutionException
	 */
	private function doApprove( string $comment ) {
		try {
			$currentPoint = $this->stabilizationLookup->getStablePointForRevision( $this->revision );
			if ( $currentPoint instanceof StablePoint ) {
				$point = $this->stabilizer->updateStablePoint( $currentPoint, $this->user, $comment );
			} else {
				$point = $this->stabilizer->addStablePoint( $this->revision, $this->user, $comment );
			}

			if ( $point === null ) {
				throw new Exception( Message::newFromKey(
					'content-stabilization-integration-activity-error-cannot-approve'
				)->text() );
			}
		} catch ( Exception $ex ) {
			throw new WorkflowExecutionException( $ex->getMessage(), $this->task );
		}
	}
}
