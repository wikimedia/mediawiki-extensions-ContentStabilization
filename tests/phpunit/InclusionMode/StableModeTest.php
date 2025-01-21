<?php

namespace MediaWiki\Extension\ContentStabilization\Tests\InclusionMode;

use File;
use HashConfig;
use MediaWiki\Extension\ContentStabilization\InclusionMode\Stable;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Extension\ContentStabilization\Storage\StablePointStore;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use PHPUnit\Framework\TestCase;
use RepoGroup;

/**
 * @covers \MediaWiki\Extension\ContentStabilization\InclusionMode\Stable
 * @group Broken To be fixed when discussion on how this should be handled happens
 *
 */
class StableModeTest extends TestCase {

	/**
	 * @param array $page
	 * @param array $image
	 * @param array $revsAtTimeOfStablization
	 * @param array $expected
	 *
	 * @dataProvider getData
	 * @covers \MediaWiki\Extension\ContentStabilization\InclusionMode\Stable::stabilizeInclusions
	 *
	 * @return void
	 */
	public function testStabilizeInclusions(
		array $page, array $image, array $revsAtTimeOfStablization, array $expected
	) {
		$revisionLookup = $this->createMock( RevisionLookup::class );
		$revisionLookup->method( 'getRevisionById' )->willReturnCallback(
			function ( $id ) {
				$revisionRecord = $this->createMock( RevisionRecord::class );
				$revisionRecord->method( 'getId' )->willReturn( $id );
				return $revisionRecord;
			}
		);

		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'makeTitle' )->willReturnCallback(
			function ( $namespace, $title ) use ( $page, $image ) {
				$pageId = 0;
				if ( $title === 'Foo' ) {
					$pageId = 2;
				} elseif ( $title === 'Foo' ) {
					$pageId = $image['page_id'];
				}

				$title = $this->createMock( Title::class );
				$title->method( 'getNamespace' )->willReturn( $namespace );
				$title->method( 'exists' )->willReturn( true );
				$title->method( 'getArticleID' )->willReturn( $pageId );
				return $title;
			}
		);

		$mainPageRevision = $this->createMock( RevisionRecord::class );
		$mainPageRevision->method( 'isCurrent' )->willReturn( true );

		$config = new HashConfig( [ 'EnabledNamespaces' => [ NS_MAIN, NS_TEMPLATE, NS_FILE ] ] );

		$store = $this->mockStore( $page, $image );
		$repoGroup = $this->mockRepoGroup( $image );
		$stableInclusionMode = new Stable( $revisionLookup, $store, $repoGroup, $titleFactory, $config );
		$stabilized = $stableInclusionMode->stabilizeInclusions( [
			'transclusions' => [
				[ 'revision' => $revsAtTimeOfStablization['page'], 'namespace' => NS_TEMPLATE, 'title' => 'Foo' ]
			],
			'images' => [ [ 'revision' => -1, 'timestamp' => $revsAtTimeOfStablization['image'], 'name' => 'Bar' ] ],
		], $mainPageRevision );

		$this->assertSame(
			$expected['page'], $stabilized['transclusions'][0]['revision'],
			'Transclusion revision should be the last stable'
		);
		$this->assertSame( $expected['image'], $stabilized['images'][0], 'Image should be the last stable' );
	}

	/**
	 * @return array[]
	 */
	public function getData() {
		return [
			'already_on_stable' => [
				'page' => [
					'page_id' => 2,
					'revisions' => [ 1, 2, 3, 4 ],
					'stable' => [ 2, 4 ],
				],
				'image' => [
					'page_id' => 3,
					'revisions' => [
						5 => [ 'timestamp' => '20220101000000', 'sha1' => '12345' ],
						6 => [ 'timestamp' => '20220101000001', 'sha1' => '12346' ],
						7 => [ 'timestamp' => '20220101000002', 'sha1' => '12347' ],
					],
					'stable' => [ 5, 7 ],
				],
				'revs_at_time_of_stablization' => [ 'page' => 2, 'image' => '20220101000000' ],
				'expected' => [
					'page' => 2,
					'image' => [
						'revision' => -1,
						'timestamp' => '20220101000000',
						'name' => 'Bar',
						'sha1' => '12345',
					]
				]
			],
			'already_on_stable_latest' => [
				'page' => [
					'page_id' => 2,
					'revisions' => [ 1, 2, 3, 4 ],
					'stable' => [ 2, 4 ],
				],
				'image' => [
					'page_id' => 3,
					'revisions' => [
						5 => [ 'timestamp' => '20220101000000', 'sha1' => '12345' ],
						6 => [ 'timestamp' => '20220101000001', 'sha1' => '12346' ],
						7 => [ 'timestamp' => '20220101000002', 'sha1' => '12347' ],
					],
					'stable' => [ 5, 7 ],
				],
				'revs_at_time_of_stablization' => [ 'page' => 4, 'image' => '20220101000002' ],
				'expected' => [
					'page' => 4,
					'image' => [
						'revision' => -1,
						'timestamp' => '20220101000002',
						'name' => 'Bar',
						'sha1' => '12347',
					]
				]
			],
			'no_stable_at_time_of_stablization' => [
				'page' => [
					'page_id' => 2,
					'revisions' => [ 1, 2, 3, 4 ],
					'stable' => [ 4 ],
				],
				'image' => [
					'page_id' => 3,
					'revisions' => [
						5 => [ 'timestamp' => '20220101000000', 'sha1' => '12345' ],
						6 => [ 'timestamp' => '20220101000001', 'sha1' => '12346' ],
						7 => [ 'timestamp' => '20220101000002', 'sha1' => '12347' ],
					],
					'stable' => [ 7 ],
				],
				'revs_at_time_of_stablization' => [ 'page' => 3, 'image' => '20220101000001' ],
				'expected' => [
					'page' => 3,
					'image' => [
						'revision' => -1,
						'timestamp' => '20220101000001',
						'name' => 'Bar',
						'sha1' => '12346',
					]
				]
			],
			'no_stable' => [
				'page' => [
					'page_id' => 2,
					'revisions' => [ 1, 2, 3, 4 ],
					'stable' => [],
				],
				'image' => [
					'page_id' => 3,
					'revisions' => [
						5 => [ 'timestamp' => '20220101000000', 'sha1' => '12345' ],
						6 => [ 'timestamp' => '20220101000001', 'sha1' => '12346' ],
						7 => [ 'timestamp' => '20220101000002', 'sha1' => '12347' ],
					],
					'stable' => [],
				],
				'revs_at_time_of_stablization' => [ 'page' => 4, 'image' => '20220101000002' ],
				'expected' => [
					'page' => 4,
					'image' => [
						'revision' => -1,
						'timestamp' => '20220101000002',
						'name' => 'Bar',
						'sha1' => '12347',
					]
				]
			]
		];
	}

	/**
	 * @param array $page
	 * @param array $image
	 *
	 * @return StablePointStore
	 */
	private function mockStore( array $page, array $image ) {
		$store = $this->createMock( StablePointStore::class );
		$store->method( 'getLatestMatchingPoint' )->willReturnCallback(
			function ( $conds ) use ( $page, $image ) {
				$revId = 0;
				foreach ( $conds as $cond ) {
					if ( strpos( $cond, 'sp_revision <= ' ) === 0 ) {
						$revId = (int)substr( $cond, strlen( 'sp_revision <= ' ) );
					}
				}

				$pageId = (int)$conds['sp_page'];
				$stableIds = [];
				if ( $pageId === $page['page_id'] ) {
					$stableIds = $page['stable'];
				} elseif ( $pageId === $image['page_id'] ) {
					$stableIds = $image['stable'];
				}
				$selectedStable = array_filter(
					$stableIds,
					static function ( $stableId ) use ( $revId ) {
						return $revId && $stableId <= $revId;
					}
				);
				$selectedRev = $selectedStable ? max( $selectedStable ) : null;
				if ( $selectedRev === null ) {
					return null;
				}
				$revisionRecord = $this->createMock( RevisionRecord::class );
				$revisionRecord->method( 'getId' )->willReturn( $selectedRev );

				$sp = $this->createMock( StablePoint::class );
				$sp->method( 'getRevision' )->willReturn( $revisionRecord );
				return $sp;
			}
		);
		return $store;
	}

	/**
	 * @param array $image
	 *
	 * @return RepoGroup
	 */
	private function mockRepoGroup( array $image ) {
		$repoGroup = $this->createMock( RepoGroup::class );
		$repoGroup->method( 'findFile' )->willReturnCallback(
			function ( $name, $options ) use ( $image ) {
				$timestamp = $options['time'] ?? null;
				$selectedRev = null;
				if ( $timestamp === null ) {
					$selectedRev = max( array_keys( $image['revisions'] ) );
				} else {
					foreach ( $image['revisions'] as $revId => $rev ) {
						if ( $rev['timestamp'] === $timestamp ) {
							$selectedRev = $revId;
						}
					}
				}

				$file = $this->createMock( File::class );
				$file->method( 'getSha1' )->willReturn( $image['revisions'][$selectedRev]['sha1'] );
				$file->method( 'getTimestamp' )->willReturn( $image['revisions'][$selectedRev]['timestamp'] );
				return $file;
			}
		);
		return $repoGroup;
	}

}
