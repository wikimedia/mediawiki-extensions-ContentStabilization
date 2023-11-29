<?php

namespace MediaWiki\Extension\ContentStabilization\Tests\Activity;

use MediaWiki\Extension\ContentStabilization\Integration\Workflows\Activity\ApprovePageActivity;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Extension\Workflows\Activity\ExecutionStatus;
use MediaWiki\Extension\Workflows\Definition\DefinitionContext;
use MediaWiki\Extension\Workflows\Definition\Element\Task;
use MediaWiki\Extension\Workflows\WorkflowContext;
use MediaWiki\Extension\Workflows\WorkflowContextMutable;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use Title;

/**
 * @covers \MediaWiki\Extension\ContentStabilization\Integration\Workflows\Activity\ApprovePageActivity
 * @group Database
 */
class ApprovePageActivityTest extends MediaWikiIntegrationTestCase {
	/** @var Title */
	private $title;
	/** @var StabilizationLookup */
	private $lookup;
	/** @var User */
	private $user;

	protected function setUp(): void {
		parent::setUp();

		$res = $this->insertPage( 'DummyApprovalTest' );
		$this->title = $res['title'];
		$this->user = $this->getTestUser( 'sysop' )->getUser();

		// Broken: this does not actually set the global
		$this->setMwGlobals( [
			'wgContentStabilizationEnabledNamespaces' => [ NS_MAIN ]
		] );
		$this->lookup = MediaWikiServices::getInstance()->getService( 'ContentStabilization.Lookup' );
	}

	/**
	 *
	 * @covers \MediaWiki\Extension\ContentStabilization\Integration\Workflows\Activity\ApprovePageActivity::execute
	 *
	 */
	public function testExecute() {
		$mutable = new WorkflowContextMutable( MediaWikiServices::getInstance()->getTitleFactory() );
		$mutable->setDefinitionContext( new DefinitionContext( [
			'pageId' => $this->title->getArticleID(),
			'revision' => $this->title->getLatestRevID()
		] ) );
		$context = new WorkflowContext( $mutable );
		$task = new Task( 'Approve1', 'Approve page', [], [], 'task' );

		$services = MediaWikiServices::getInstance();
		$activity = new ApprovePageActivity(
			$services->getTitleFactory(),
			$services->getService( 'ContentStabilization.Stabilizer' ),
			$services->getService( 'ContentStabilization.Lookup' ),
			$services->getRevisionStore(),
			$services->getUserFactory(),
			$task
		);

		$lastStable = $this->lookup->getLastStablePoint( $this->title->toPageIdentity() );
		$lastStableId = $lastStable ? $lastStable->getRevision()->getId() : null;
		$this->assertNotEquals( $this->title->getLatestRevID(), $lastStableId );
		$status = $activity->execute( [
			'comment' => 'Dummy comment',
			'user' => $this->user->getName()
		], $context );

		$this->assertInstanceOf(
			ExecutionStatus::class, $status, 'Activity should return an ExecutionStatus'
		);
		$lastStable = $this->lookup->getLastStablePoint( $this->title->toPageIdentity() );
		$this->assertInstanceOf( StablePoint::class, $lastStable );
		$this->assertEquals( $this->title->getLatestRevID(), $lastStable->getRevision()->getId() );
	}
}
