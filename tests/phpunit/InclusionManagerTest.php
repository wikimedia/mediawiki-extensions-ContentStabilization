<?php

namespace MediaWiki\Extension\ContentStabilization\Tests;

use File;
use HashConfig;
use LocalRepo;
use MediaWiki\Content\Content;
use MediaWiki\Extension\ContentStabilization\InclusionManager;
use MediaWiki\Extension\ContentStabilization\InclusionMode;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use Parser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RepoGroup;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use WikiPage;

/**
 * I tried to make this test as readable as possible,
 * but it's still a big test, with complicated data resolutions
 *
 * @covers \MediaWiki\Extension\ContentStabilization\InclusionManager
 */
class InclusionManagerTest extends TestCase {
	/* Getting data to test on */

	/**
	 * @return array[]
	 */
	private function getParserOutputData(): array {
		return [
			'PageToTest' => [
				'images' => [ 'Foo.png' => 1, 'Bar.png' => 2 ],
				'transclusions' => [ NS_MAIN => [ 'Foo' => 3, 'Bar' => 4 ] ]
			],
		];
	}

	/**
	 * @return array
	 */
	private function getRevisionIds(): array {
		return [
			// Inclusions
			3 => [ 2, 3, 4 ],
			4 => [ 5, 6, 7 ],
			1 => [ 8, 9 ],
			2 => [ 10, 11 ],
		];
	}

	/**
	 * @return array
	 */
	private function getImages(): array {
		return [
			'Foo.png' => [
				8 => [ 'timestamp' => '20190101000000', 'sha1' => 'a11234567890' ],
				9 => [ 'timestamp' => '20200101000000', 'sha1' => 'a21234567890' ],
			],
			'Bar.png' => [
				10 => [ 'timestamp' => '20200103000000', 'sha1' => 'b11234567890' ],
				11 => [ 'timestamp' => '20210104000000', 'sha1' => 'b21234567890' ],
			]
		];
	}

	/**
	 * @return array
	 */
	private function getLatestExpectedInclusions(): array {
		return [
			// Highest revision from `getRevisionIds`
			'transclusions' => [
				[ 'revision' => 4, 'namespace' => NS_MAIN, 'title' => 'Foo' ],
				[ 'revision' => 7, 'namespace' => NS_MAIN, 'title' => 'Bar' ]
			],
			// Data from the highest revision from `getImages`
			'images' => [
				[ 'revision' => 9, 'name' => 'Foo.png', 'timestamp' => '20200101000000', 'sha1' => 'a21234567890' ],
				[ 'revision' => 11, 'name' => 'Bar.png', 'timestamp' => '20210104000000', 'sha1' => 'b21234567890' ],
			],
		];
	}

	/* Actual testing */

	/**
	 * This method will insert data of the stable inclusions. Result of this method
	 * is the data of the stabilized inclusions, same as when calling `getStableInclusions`
	 *
	 * @covers \MediaWiki\Extension\ContentStabilization\InclusionManager::stabilizeInclusions
	 * @covers \MediaWiki\Extension\ContentStabilization\InclusionManager::getStableInclusions
	 * @return void
	 */
	public function testStabilizeInclusions() {
		$pages = $this->getPageMocks( [ 'PageToTest' ] );
		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPageAsLinkTarget' )->willReturn( $pages[0] );
		$revision->method( 'getId' )->willReturn( 1 );
		$revision->method( 'getPageId' )->willReturn( 2 );

		// Based on current inclusions, we expect DB to be called with certain parameters
		// and that it returns certain data
		$lb = $this->mockLoadBalancer();
		$connection = $lb->getConnection( DB_PRIMARY );

		// Expect to clear out old data before settings new data
		$expectedDeleteArgs = [
			[ 'stable_transclusions', [ 'st_revision' => 1 ], InclusionManager::class . '::storeTransclusions' ],
			[ 'stable_file_transclusions', [ 'sft_revision' => 1 ], InclusionManager::class . '::storeImages' ]
		];
		$connection->expects( $this->exactly( 2 ) )
			->method( 'delete' )
			->willReturnCallback( function ( $table, $conds, $fname ) use ( &$expectedDeleteArgs ) {
				$curExpectedArgs = array_shift( $expectedDeleteArgs );
				$this->assertSame( $curExpectedArgs[0], $table );
				$this->assertSame( $curExpectedArgs[1], $conds );
				$this->assertSame( $curExpectedArgs[2], $fname );
			} );

		$dataExpectedToInsert = [
			[
				'table' => 'stable_transclusions',
				'records' => [
					[
						'st_revision' => 1, 'st_page' => 2,
						'st_transclusion_revision' => 4,
						'st_transclusion_namespace' => NS_MAIN,
						'st_transclusion_title' => 'Foo',
					], [
						'st_revision' => 1, 'st_page' => 2,
						'st_transclusion_revision' => 7,
						'st_transclusion_namespace' => NS_MAIN,
						'st_transclusion_title' => 'Bar',
					],
				],
			],
			[
				'table' => 'stable_file_transclusions',
				'records' => [
					[
						'sft_revision' => 1, 'sft_page' => 2,
						'sft_file_name' => 'Foo.png',
						'sft_file_timestamp' => '20200101000000',
						'sft_file_sha1' => 'a21234567890',
						'sft_file_revision' => 9,
					], [
						'sft_revision' => 1, 'sft_page' => 2,
						'sft_file_name' => 'Bar.png',
						'sft_file_timestamp' => '20210104000000',
						'sft_file_sha1' => 'b21234567890',
						'sft_file_revision' => 11,
					],
				],
			]
		];

		// Inserting current state
		$expectedInsertArgs = [
			[
				$dataExpectedToInsert[0]['table'],
				$dataExpectedToInsert[0]['records'],
				InclusionManager::class . '::storeTransclusions',
			],
			[
				$dataExpectedToInsert[1]['table'],
				$dataExpectedToInsert[1]['records'],
				InclusionManager::class . '::storeImages',
				[ 'IGNORE' ]
			]
		];
		$connection->expects( $this->exactly( 2 ) )
			->method( 'insert' )
			->willReturnCallback( function ( $table, $rows, $fname ) use ( &$expectedInsertArgs ) {
				$curExpectedArgs = array_shift( $expectedInsertArgs );
				$this->assertSame( $curExpectedArgs[0], $table );
				$this->assertSame( $curExpectedArgs[1], $rows );
				$this->assertSame( $curExpectedArgs[2], $fname );
			} );

		$connection->method( 'select' )->willReturnCallback( static function ( $table ) use ( $dataExpectedToInsert ) {
			foreach ( $dataExpectedToInsert as $data ) {
				if ( $data['table'] === $table ) {
					return new FakeResultWrapper( $data['records'] );
				}
			}
			return [];
		} );

		$inclusionManager = $this->getInclusionManager( $lb );
		$stabilized = $inclusionManager->stabilizeInclusions( $revision );
		$this->assertEquals( $this->getLatestExpectedInclusions(), $stabilized );
	}

	/**
	 * @covers \MediaWiki\Extension\ContentStabilization\InclusionManager::getCurrentStabilizedInclusions
	 * @return void
	 */
	public function testGetCurrentStabilizedInclusions() {
		$pages = $this->getPageMocks( [ 'PageToTest' ] );
		$expected = $this->getLatestExpectedInclusions();

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPageAsLinkTarget' )->willReturn( $pages[0] );

		$inclusionModeMock = $this->createMock( InclusionMode::class );
		$inclusionModeMock->method( 'stabilizeInclusions' )->willReturnCallback(
			static function ( $inclusion, $revision ) {
				// Just return as is
				return $inclusion;
			}
		);
		// We expect the inclusionMode to be called once
		$inclusionModeMock->expects( $this->once() )
			->method( 'stabilizeInclusions' )
			->with( $expected, $revision );

		$inclusionManager = $this->getInclusionManager( null, $inclusionModeMock );
		$inclusions = $inclusionManager->getCurrentStabilizedInclusions( $revision );
		$this->assertSame( $expected, $inclusions );
	}

	/**
	 * @covers \MediaWiki\Extension\ContentStabilization\InclusionManager::removeStableInclusionsForRevision
	 */
	public function testRemoveStableInclusionsForRevision() {
		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getId' )->willReturn( 1 );

		$lb = $this->mockLoadBalancer();
		$connection = $lb->getConnection( DB_PRIMARY );

		// Expect to clear out old data before settings new data
		$expectedDeleteArgs = [
			[
				'stable_transclusions',
				[ 'st_revision' => 1 ],
				InclusionManager::class . '::removeStableInclusionsForRevision'
			],
			[
				'stable_file_transclusions',
				[ 'sft_revision' => 1 ],
				InclusionManager::class . '::removeStableInclusionsForRevision'
			]
		];
		$connection->expects( $this->exactly( 2 ) )
			->method( 'delete' )
			->willReturnCallback( function ( $table, $conds, $fname ) use ( &$expectedDeleteArgs ) {
				$curExpectedArgs = array_shift( $expectedDeleteArgs );
				$this->assertSame( $curExpectedArgs[0], $table );
				$this->assertSame( $curExpectedArgs[1], $conds );
				$this->assertSame( $curExpectedArgs[2], $fname );
			} );

		$inclusionManager = $this->getInclusionManager( $lb );
		$inclusionManager->removeStableInclusionsForRevision( $revision );
	}

	/**
	 * @covers \MediaWiki\Extension\ContentStabilization\InclusionManager::removeStableInclusionsForPage
	 */
	public function testRemoveStableInclusionsForPage() {
		$page = $this->createMock( PageIdentity::class );
		$page->method( 'getId' )->willReturn( 2 );

		$lb = $this->mockLoadBalancer();
		$connection = $lb->getConnection( DB_PRIMARY );

		// Expect to clear out old data before settings new data
		$expectedDeleteArgs = [
			[
				'stable_transclusions',
				[ 'st_page' => 2 ],
				InclusionManager::class . '::removeStableInclusionsForPage'
			],
			[
				'stable_file_transclusions',
				[ 'sft_page' => 2 ],
				InclusionManager::class . '::removeStableInclusionsForPage'
			]
		];
		$connection->expects( $this->exactly( 2 ) )
			->method( 'delete' )
			->willReturnCallback( function ( $table, $conds, $fname ) use ( &$expectedDeleteArgs ) {
				$curExpectedArgs = array_shift( $expectedDeleteArgs );
				$this->assertSame( $curExpectedArgs[0], $table );
				$this->assertSame( $curExpectedArgs[1], $conds );
				$this->assertSame( $curExpectedArgs[2], $fname );
			} );

		$inclusionManager = $this->getInclusionManager( $lb );
		$inclusionManager->removeStableInclusionsForPage( $page );
	}

	/* Mocks and helper methods, nothing interesting after this point */

	/**
	 * @param ILoadBalancer|null $lb
	 * @param InclusionMode|null $inclusionModeMock
	 *
	 * @return InclusionManager
	 */
	private function getInclusionManager(
		?ILoadBalancer $lb = null, ?InclusionMode $inclusionModeMock = null
	): InclusionManager {
		return new InclusionManager(
			$lb ?? $this->mockLoadBalancer(),
			$this->mockWikiPageFactory(),
			$this->mockRevisionLookup(),
			$this->mockRepoGroup(),
			new HashConfig( [ 'InclusionMode' => 'default' ] ),
			$this->getParserFactoryMock(),
			[ 'default' => $inclusionModeMock ]
		);
	}

	/**
	 * @param array $data
	 *
	 * @return array
	 */
	private function getPageMocks( array $data ): array {
		$pages = [];
		foreach ( $data as $dbKey ) {
			$page = $this->createMock( LinkTarget::class );
			$page->method( 'getDBkey' )->willReturn( $dbKey );
			$pages[] = $page;
		}

		return $pages;
	}

	/**
	 * @param LinkTarget $page
	 *
	 * @return ParserOutput
	 */
	private function getParserOutput( LinkTarget $page ): ParserOutput {
		$output = $this->createMock( ParserOutput::class );
		$currentInclusions = $this->getParserOutputData();
		$output->method( 'getTemplates' )->willReturnCallback(
			static function () use ( $page, $currentInclusions ) {
				if ( !isset( $currentInclusions[$page->getDBkey()] ) ) {
					return [];
				}
				return $currentInclusions[$page->getDBkey()]['transclusions'];
			} );
		$output->method( 'getImages' )->willReturnCallback(
			static function () use ( $page, $currentInclusions ) {
				if ( !isset( $currentInclusions[$page->getDBkey()] ) ) {
					return [];
				}
				return $currentInclusions[$page->getDBkey()]['images'];
			} );

		return $output;
	}

	/**
	 * @return ILoadBalancer&MockObject
	 */
	private function mockLoadBalancer() {
		$mock = $this->getMockBuilder( ILoadBalancer::class )
			->disableOriginalConstructor()
			->getMock();
		$connMock = $this->getMockBuilder( IDatabase::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'getConnectionRef' )->willReturn( $connMock );

		return $mock;
	}

	/**
	 * @return WikiPageFactory&MockObject
	 */
	private function mockWikiPageFactory() {
		$mock = $this->getMockBuilder( WikiPageFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'newFromLinkTarget' )->willReturnCallback( function ( LinkTarget $target ) {
			$wikipageMock = $this->createMock( WikiPage::class );
			$wikipageMock->method( 'makeParserOptions' )->willReturn(
				$this->createMock( ParserOptions::class )
			);
			$contentMock = $this->createMock( Content::class );
			$contentMock->method( 'getWikitextForTransclusion' )->willReturn( '' );
			$wikipageMock->method( 'getContent' )->willReturn( $contentMock );
			$titleMock = $this->createMock( Title::class );
			$titleMock->method( 'getNamespace' )->willReturn( $target->getNamespace() );
			$titleMock->method( 'getDBkey' )->willReturn( $target->getDBkey() );
			$titleMock->method( 'getText' )->willReturn( $target->getText() );
			$wikipageMock->method( 'getTitle' )->willReturn( $titleMock );
			return $wikipageMock;
		} );
		return $mock;
	}

	/**
	 * @return ParserFactory&MockObject|MockObject
	 */
	private function getParserFactoryMock() {
		$parserMock = $this->createMock( Parser::class );
		$parserMock->method( 'parse' )->willReturnCallback(
			function ( $text, Title $title, ParserOptions $options ) {
				return $this->getParserOutput( $title );
			}
		);

		$mock = $this->getMockBuilder( ParserFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'getMainInstance' )->willReturn( $parserMock );

		return $mock;
	}

	/**
	 * @return RevisionLookup&MockObject|MockObject
	 */
	private function mockRevisionLookup() {
		$mock = $this->getMockBuilder( RevisionLookup::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'getRevisionByPageId' )->willReturnCallback( function ( $pageId ) {
			$mock = $this->getMockBuilder( RevisionRecord::class )
				->disableOriginalConstructor()
				->getMock();
			$mock->method( 'getId' )->willReturnCallback( function () use ( $pageId ) {
				$revisionIds = $this->getRevisionIds();
				if ( !isset( $revisionIds[$pageId] ) ) {
					return null;
				}
				return max( $revisionIds[$pageId] );
			} );
			$mock->method( 'getPageId' )->willReturn( $pageId );
			return $mock;
		} );

		return $mock;
	}

	/**
	 * @return MockObject|RepoGroup&MockObject
	 */
	private function mockRepoGroup() {
		$imageInfo = $this->getImages();
		$localRepoMock = $this->getMockBuilder( LocalRepo::class )
			->disableOriginalConstructor()
			->getMock();
		$localRepoMock->method( 'findFile' )->willReturnCallback( function ( $name )  use ( $imageInfo ) {
			$image = $imageInfo[$name] ?? null;
			if ( !$image ) {
				return null;
			}
			$revId = max( array_keys( $image ) );
			$image = $image[$revId];
			$imageMock = $this->getMockBuilder( File::class )
				->disableOriginalConstructor()
				->getMock();
			$imageMock->method( 'getTitle' )->willReturnCallback( function () use ( $revId ) {
				$titleMock = $this->getMockBuilder( Title::class )
					->disableOriginalConstructor()
					->getMock();
				$titleMock->method( 'getLatestRevID' )->willReturn( $revId );
				return $titleMock;
			} );
			$imageMock->method( 'getName' )->willReturn( $name );
			$imageMock->method( 'getTimestamp' )->willReturn( $image['timestamp'] );
			$imageMock->method( 'getSha1' )->willReturn( $image['sha1'] );
			return $imageMock;
		} );

		$repoGroupMock = $this->getMockBuilder( RepoGroup::class )
			->disableOriginalConstructor()
			->getMock();
		$repoGroupMock->method( 'getLocalRepo' )->willReturn( $localRepoMock );

		return $repoGroupMock;
	}
}
