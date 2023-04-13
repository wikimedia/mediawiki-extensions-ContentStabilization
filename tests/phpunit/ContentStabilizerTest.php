<?php

namespace MediaWiki\Extension\ContentStabilization\Tests;

use InvalidArgumentException;
use MediaWiki\Extension\ContentStabilization\ContentStabilizer;
use MediaWiki\Extension\ContentStabilization\InclusionManager;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Extension\ContentStabilization\Storage\StablePointStore;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;
use PHPUnit\Framework\TestCase;

/**
 *
 * @covers \MediaWiki\Extension\ContentStabilization\ContentStabilizer
 */
class ContentStabilizerTest extends TestCase {
	/**
	 * @covers \MediaWiki\Extension\ContentStabilization\ContentStabilizer::addStablePoint
	 * @dataProvider provideRevisions
	 */
	public function testAddStablePoint( $username, $isCurrent, $isStable, $expectException ) {
		$inclusionManager = $this->getMockBuilder( InclusionManager::class )
			->disableOriginalConstructor()
			->getMock();
		$stablePointStore = $this->getMockBuilder( StablePointStore::class )
			->disableOriginalConstructor()
			->getMock();
		$stablePointStore->method( 'getLatestMatchingPoint' )->willReturnCallback(
			function ( $revision ) use ( $isStable ) {
				if ( !$isStable ) {
					return null;
				}
				return $this->getMockBuilder( StablePoint::class )
					->disableOriginalConstructor()
					->getMock();
			}
		);
		$stablizer = new ContentStabilizer(
			$stablePointStore,
			$this->mockStabilizationLookup(),
			$inclusionManager,
			$this->mockPermissionManager(),
			$this->createMock( HookContainer::class )
		);

		$revision = $this->getMockBuilder( RevisionRecord::class )
			->disableOriginalConstructor()
			->getMock();
		$revision->method( 'getId' )->willReturn( 1 );
		$revision->method( 'isCurrent' )->willReturn( $isCurrent );
		$revision->method( 'getPageAsLinkTarget' )->willReturn( $this->createMock( LinkTarget::class ) );

		$user = $this->getMockBuilder( \User::class )
			->disableOriginalConstructor()
			->getMock();
		$user->method( 'getName' )->willReturn( $username );
		$user->method( 'isRegistered' )->willReturn( true );
		$user->method( 'getUser' )->willReturn( $user );

		if ( $expectException ) {
			$this->expectException( InvalidArgumentException::class );
		} else {
			$inclusionManager->expects( $this->once() )->method( 'stabilizeInclusions' );
			$stablePointStore->expects( $this->once() )->method( 'insertStablePoint' );
		}
		$stablizer->addStablePoint( $revision, $user, 'Comment' );
	}

	/**
	 * @param string $username
	 * @param bool $expectException
	 *
	 * @return void
	 * @covers       \MediaWiki\Extension\ContentStabilization\ContentStabilizer::removeStablePoint
	 * @dataProvider provideStablePoints
	 *
	 */
	public function testRemoveStablePoint( $username, bool $expectException ) {
		$inclusionManager = $this->getMockBuilder( InclusionManager::class )
			->disableOriginalConstructor()
			->getMock();
		$stablePointStore = $this->getMockBuilder( StablePointStore::class )
			->disableOriginalConstructor()
			->getMock();

		$point = $this->getMockBuilder( StablePoint::class )
			->disableOriginalConstructor()
			->getMock();
		$point->method( 'getRevision' )->willReturnCallback( function () {
			$revision = $this->getMockBuilder( RevisionRecord::class )
				->disableOriginalConstructor()
				->getMock();
			$revision->method( 'getPageAsLinkTarget' )->willReturn( $this->createMock( LinkTarget::class ) );
			return $revision;
		} );

		$stablizer = new ContentStabilizer(
			$stablePointStore,
			$this->mockStabilizationLookup(),
			$inclusionManager,
			$this->mockPermissionManager(),
			$this->createMock( HookContainer::class )
		);

		$user = $this->getMockBuilder( \User::class )
			->disableOriginalConstructor()
			->getMock();
		$user->method( 'getName' )->willReturn( $username );
		$user->method( 'isRegistered' )->willReturn( true );
		$user->method( 'getUser' )->willReturn( $user );

		if ( $expectException ) {
			$this->expectException( InvalidArgumentException::class );
		} else {
			$inclusionManager->expects( $this->once() )->method( 'removeStableInclusionsForRevision' );
			$stablePointStore->expects( $this->once() )->method( 'removeStablePoint' );
		}
		$stablizer->removeStablePoint( $point, $user );
	}

	/**
	 * @return StabilizationLookup&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function mockStabilizationLookup() {
		$mock = $this->getMockBuilder( StabilizationLookup::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'getStablePointForRevision' )->willReturn(
			$this->getMockBuilder( StablePoint::class )
				->disableOriginalConstructor()
				->getMock()
		);
		$mock->method( 'isStabilizationEnabled' )->willReturn( true );
		return $mock;
	}

	/**
	 * @return PermissionManager&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function mockPermissionManager() {
		$mock = $this->getMockBuilder( PermissionManager::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'userCan' )->willReturnCallback( static function ( $action, $user, $title ) {
			if ( $user->getName() === 'reader' ) {
				return false;
			}
			return true;
		} );
		return $mock;
	}

	/**
	 * @return array[]
	 */
	public function provideRevisions() {
		return [
			'already-stable' => [
				'user' => 'admin',
				'isCurrent' => true,
				'isStable' => true,
				'expectException' => true
			],
			'not-current' => [
				'user' => 'admin',
				'isCurrent' => false,
				'isStable' => false,
				'expectException' => true
			],
			'valid' => [
				'user' => 'admin',
				'isCurrent' => true,
				'isStable' => false,
				'expectException' => false
			],
			'user-no-permission' => [
				'user' => 'reader',
				'isCurrent' => true,
				'isStable' => false,
				'expectException' => true
			],
		];
	}

	/**
	 * @return array[]
	 */
	public function provideStablePoints() {
		return [
			'valid' => [
				'username' => 'admin',
				'expectException' => false
			],
			'user-no-permission' => [
				'username' => 'reader',
				'expectException' => true
			],
		];
	}
}
