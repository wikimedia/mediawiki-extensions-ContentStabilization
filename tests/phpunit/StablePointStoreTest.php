<?php

namespace MediaWiki\Extension\ContentStabilization\Tests;

use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Extension\ContentStabilization\Storage\StablePointStore;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserFactory;
use PHPUnit\Framework\TestCase;
use RepoGroup;
use User;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 *
 * @covers \MediaWiki\Extension\ContentStabilization\Storage\StablePointStore
 */
class StablePointStoreTest extends TestCase {
	/**
	 * @covers \MediaWiki\Extension\ContentStabilization\Storage\StablePointStore::query
	 */
	public function testQuery() {
		$lb = $this->mockLoadBalancer();

		$lb->getConnection( DB_REPLICA )->method( 'select' )->willReturn(
			new FakeResultWrapper( [
				(object)[
					'sp_revision' => '1',
					'sp_time' => '20190101000000',
					'sp_user' => '1',
					'sp_comment' => 'Comment'
				],
				(object)[
					'sp_revision' => '2',
					'sp_time' => '20190101000000',
					'sp_user' => '2',
					'sp_comment' => 'Comment'
				],
			] )
		);
		$lb->getConnection( DB_REPLICA )->expects( $this->once() )->method( 'select' )->with(
			[ 'stable_points', 'stable_file_points' ],
			[ 'sp_page', 'sp_revision', 'sp_time', 'sp_user', 'sp_comment', 'sfp_file_timestamp', 'sfp_file_sha1' ],
			[ 'sp_page' => 1 ],
			StablePointStore::class . '::rawQuery',
			[ 'ORDER BY' => 'sp_revision DESC' ]
		);

		$userFactory = $this->mockUserFactory();
		$store = new StablePointStore(
			$lb, $userFactory, $this->mockRevisionStore(), $this->createMock( \RepoGroup::class )
		);
		$points = $store->query( [ 'sp_page' => 1 ] );

		$this->assertCount( 2, $points );
		$this->assertSame( 1, $points[0]->getRevision()->getId() );
		$this->assertEquals( 2, $points[1]->getRevision()->getId() );
		$this->assertSame( 1, $points[0]->getApprover()->getId() );
		$this->assertEquals( 2, $points[1]->getApprover()->getId() );
		$this->assertEquals( 'Comment', $points[1]->getComment() );
		$this->assertSame( '20190101000000', $points[0]->getTime()->format( 'YmdHis' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\ContentStabilization\Storage\StablePointStore::getLatestMatchingPoint
	 */
	public function testGetLatestMatchingPoint() {
		$lb = $this->mockLoadBalancer();

		$lb->getConnection( DB_REPLICA )->method( 'select' )->willReturn(
			new FakeResultWrapper( [
				(object)[
					'sp_revision' => '1',
					'sp_time' => '20190101000000',
					'sp_user' => '1',
					'sp_comment' => 'Comment'
				]
			] )
		);
		$lb->getConnection( DB_REPLICA )->expects( $this->once() )->method( 'select' )->with(
			[ 'stable_points', 'stable_file_points' ],
			[ 'sp_page', 'sp_revision', 'sp_time', 'sp_user', 'sp_comment', 'sfp_file_timestamp', 'sfp_file_sha1' ],
			[ 'sp_page' => 1 ],
			StablePointStore::class . '::rawQuery',
			[ 'ORDER BY' => 'sp_revision DESC' ]
		);

		$userFactory = $this->mockUserFactory();
		$store = new StablePointStore(
			$lb, $userFactory, $this->mockRevisionStore(), $this->createMock( \RepoGroup::class )
		);
		$point = $store->getLatestMatchingPoint( [ 'sp_page' => 1 ] );

		$this->assertInstanceOf( StablePoint::class, $point );
		$this->assertSame( 1, $point->getRevision()->getId() );
		$this->assertSame( 1, $point->getApprover()->getId() );
		$this->assertSame( '20190101000000', $point->getTime()->format( 'YmdHis' ) );
	}

	/**
	 * @covers \MediaWiki\Extension\ContentStabilization\Storage\StablePointStore::insertStablePoint
	 */
	public function testInsertStablePoint() {
		$lb = $this->mockLoadBalancer();

		$lb->getConnection( DB_REPLICA )->method( 'timestamp' )->willReturn( '20190101000000' );
		$lb->getConnection( DB_REPLICA )->method( 'insert' )->willReturn( true );
		$lb->getConnection( DB_REPLICA )->expects( $this->once() )->method( 'insert' )->with(
			'stable_points',
			[
				'sp_page' => 1,
				'sp_revision' => 1,
				'sp_time' => '20190101000000',
				'sp_user' => 2,
				'sp_comment' => 'Comment'
			],
			StablePointStore::class . '::insertStablePoint'
		);

		$user = $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->getMock();
		$user->method( 'getId' )->willReturn( 2 );
		$user->method( 'isRegistered' )->willReturn( true );
		$user->method( 'getUser' )->willReturn( $user );

		$revision = $this->getMockBuilder( RevisionRecord::class )
			->disableOriginalConstructor()
			->getMock();
		$revision->method( 'getId' )->willReturn( 1 );
		$revision->method( 'getPage' )->willReturnCallback( function () {
			$page = $this->getMockBuilder( PageIdentity::class )->disableOriginalConstructor()->getMock();
			$page->method( 'getId' )->willReturn( 1 );
			return $page;
		} );

		$store = new StablePointStore(
			$lb, $this->mockUserFactory(), $this->mockRevisionStore(), $this->createMock( \RepoGroup::class )
		);
		$store->insertStablePoint( $revision, $user, 'Comment' );
	}

	/**
	 * @covers \MediaWiki\Extension\ContentStabilization\Storage\StablePointStore::removeStablePoint
	 */
	public function testRemoveStablePoint() {
		$lb = $this->mockLoadBalancer();

		$lb->getConnection( DB_REPLICA )->method( 'delete' )->willReturn( true );
		$expectedDeleteArgs = [
			[
				'stable_points',
				[
					'sp_page' => 1,
					'sp_revision' => 1,
				],
				StablePointStore::class . '::removeStablePoint'
			],
			[
				'stable_file_points',
				[
					'sfp_revision' => 1,
				],
				StablePointStore::class . '::removeStablePoint'
			]
		];
		$lb->getConnectionRef( DB_REPLICA )->expects( $this->exactly( 2 ) )->method( 'delete' )
			->willReturnCallback( function ( $table, $conds, $fname ) use ( &$expectedDeleteArgs ) {
				$curExpectedArgs = array_shift( $expectedDeleteArgs );
				$this->assertSame( $curExpectedArgs[0], $table );
				$this->assertSame( $curExpectedArgs[1], $conds );
				$this->assertSame( $curExpectedArgs[2], $fname );
			} );

		$revision = $this->getMockBuilder( RevisionRecord::class )
			->disableOriginalConstructor()
			->getMock();
		$revision->method( 'getId' )->willReturn( 1 );
		$revision->method( 'getPage' )->willReturnCallback( function () {
			$page = $this->getMockBuilder( PageIdentity::class )->disableOriginalConstructor()->getMock();
			$page->method( 'getId' )->willReturn( 1 );
			return $page;
		} );

		$stablePoint = $this->getMockBuilder( StablePoint::class )
			->disableOriginalConstructor()
			->getMock();
		$stablePoint->method( 'getRevision' )->willReturn( $revision );

		$store = new StablePointStore(
			$lb, $this->mockUserFactory(), $this->mockRevisionStore(), $this->createMock( \RepoGroup::class )
		);
		$store->removeStablePoint( $stablePoint );
	}

	/**
	 * @covers \MediaWiki\Extension\ContentStabilization\Storage\StablePointStore::updateStablePoint
	 */
	public function testUpdateStablePoint() {
		$lb = $this->mockLoadBalancer();

		$lb->getConnection( DB_REPLICA )->method( 'timestamp' )->willReturn( '20190101000000' );
		$lb->getConnection( DB_REPLICA )->method( 'update' )->willReturn( true );
		$lb->getConnection( DB_REPLICA )->expects( $this->once() )->method( 'update' )->with(
			'stable_points',
			[
				'sp_revision' => 2,
				'sp_user' => 3,
				'sp_time' => '20190101000000',
				'sp_comment' => 'CommentFoo',
			],
			[
				'sp_page' => 1,
				'sp_revision' => 1,
			],
			StablePointStore::class . '::updateStablePoint'
		);

		$newRevision = $this->getMockBuilder( RevisionRecord::class )
			->disableOriginalConstructor()
			->getMock();
		$newRevision->method( 'getId' )->willReturn( 2 );

		$stablePoint = $this->getMockBuilder( StablePoint::class )
			->disableOriginalConstructor()
			->getMock();
		$stablePoint->method( 'getRevision' )->willReturnCallback( function () {
			$revision = $this->getMockBuilder( RevisionRecord::class )
				->disableOriginalConstructor()
				->getMock();
			$revision->method( 'getId' )->willReturn( 1 );
			$revision->method( 'getPage' )->willReturnCallback( function () {
				$page = $this->getMockBuilder( PageIdentity::class )->disableOriginalConstructor()->getMock();
				$page->method( 'getId' )->willReturn( 1 );
				return $page;
			} );
			return $revision;
		} );

		$user = $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->getMock();
		$user->method( 'getId' )->willReturn( 3 );
		$user->method( 'isRegistered' )->willReturn( true );
		$user->method( 'getUser' )->willReturn( $user );

		$store = new StablePointStore(
			$lb, $this->mockUserFactory(), $this->mockRevisionStore(), $this->createMock( RepoGroup::class )
		);
		$store->updateStablePoint( $stablePoint, $newRevision, $user, 'CommentFoo' );
	}

	/**
	 * @return UserFactory&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function mockUserFactory() {
		$userFactory = $this->getMockBuilder( UserFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$userFactory->method( 'newFromId' )->willReturnCallback( function ( $id ) {
			$user = $this->getMockBuilder( User::class )
				->disableOriginalConstructor()
				->getMock();
			$user->method( 'getId' )->willReturn( (int)$id );
			$user->method( 'isRegistered' )->willReturn( true );
			$user->method( 'getUser' )->willReturn( $user );
			return $user;
		} );
		return $userFactory;
	}

	/**
	 * @return \PHPUnit\Framework\MockObject\MockObject&ILoadBalancer
	 */
	private function mockLoadBalancer() {
		$lb = $this->getMockBuilder( ILoadBalancer::class )
			->disableOriginalConstructor()
			->getMock();
		$conn = $this->getMockBuilder( IDatabase::class )
			->disableOriginalConstructor()
			->getMock();
		$lb->method( 'getConnectionRef' )->willReturn( $conn );

		return $lb;
	}

	/**
	 * @return RevisionStore&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function mockRevisionStore() {
		$mock = $this->getMockBuilder( RevisionStore::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'getRevisionById' )->willReturnCallback( function ( $id ) {
			$revision = $this->getMockBuilder( RevisionRecord::class )
				->disableOriginalConstructor()
				->getMock();
			$revision->method( 'getId' )->willReturn( (int)$id );
			return $revision;
		} );
		return $mock;
	}
}
