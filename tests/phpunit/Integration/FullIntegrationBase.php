<?php

namespace MediaWiki\Extension\ContentStabilization\Tests\Integration;

use Article;
use FauxRequest;
use MediaWiki\Extension\ContentStabilization\ContentStabilizer;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use MWException;
use OutputPage;
use PermissionsError;
use RequestContext;
use User;

abstract class FullIntegrationBase extends MediaWikiIntegrationTestCase {
	/** @var User */
	protected $testUser;
	/** @var Title */
	protected $pageToTest;
	/** @var Title */
	protected $templatePage;
	/** @var ContentStabilizer */
	protected $stabilizer;
	/** @var StabilizationLookup */
	protected $lookup;

	protected function setUp(): void {
		parent::setUp();
		// Allow anons to read (let CS stabilize the content instead of the read permission)
		$this->setGroupPermissions( '*', 'read', true );
		$GLOBALS['bsgGroupRoles']['*']['reader'] = true;

		$this->overrideConfigValues( [
			MainConfigNames::ParserCacheType => CACHE_NONE,
			'ContentStabilizationEnabledNamespaces' => $this->getEnabledNamespaces(),
			'ContentStabilizationAllowFirstUnstable' => $this->shouldAllowFirstUnstable(),
			// Default - freeze
			'ContentStabilizationInclusionMode' => $this->getInclusionMode(),
		] );

		$res = $this->insertPage( 'Template:TestStabilization', 'T1', NS_TEMPLATE );
		$this->templatePage = $res['title'];
		$res = $this->insertPage( 'TestStabilization', "V1{{TestStabilization}}" );
		$this->pageToTest = $res['title'];

		$this->testUser = $this->getTestSysop()->getUser();
		// Do not use parser cache - apparently setting the global does not do the trick
		$this->getServiceContainer()->getHookContainer()->register( 'ArticleViewHeader', static function (
			$article, &$outputDone, &$pcache
		) {
			$pcache = false;
		} );

		$this->stabilizer = $this->getServiceContainer()->getService( 'ContentStabilization.Stabilizer' );
		$this->lookup = $this->getServiceContainer()->getService( 'ContentStabilization.Lookup' );
		// Needed for the test but not in real life, since here we have a lot of edits in the same request
		$this->lookup->setUseCache( false );
	}

	/**
	 * @return string|null
	 */
	abstract protected function getInclusionMode(): ?string;

	/**
	 * @return array
	 */
	abstract protected function getEnabledNamespaces(): array;

	/**
	 * @return bool
	 */
	abstract protected function shouldAllowFirstUnstable(): bool;

	/**
	 * @param User $user
	 * @param string|null $expected
	 * @param array|null $requestData
	 * @param string $message
	 * @param Title|null $title
	 *
	 * @return void
	 * @throws MWException
	 * @throws PermissionsError
	 */
	protected function assertOutputContains( User $user, $expected, $requestData = [], $message = '', $title = null ) {
		$title = $title ?? $this->pageToTest;

		$fauxRequest = new FauxRequest( [ 'title' => $title->getPrefixedDBkey() ] + $requestData );

		$context = new RequestContext();
		$context->setRequest( $fauxRequest );
		$context->setUser( $user );
		$context->setTitle( $title );
		$outputPage = new OutputPage( $context );
		$context->setOutput( $outputPage );
		$outputPage->setArticleBodyOnly( true );

		$thrown = false;
		try {
			$article = Article::newFromTitle( $title, $context );
			$article->view();
		} catch ( PermissionsError $e ) {
			$thrown = true;
		}

		if ( $expected ) {
			$this->assertStringContainsString( $expected, $outputPage->getHTML(), $message );
		} else {
			$this->assertTrue( $thrown );
		}
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		parent::tearDown();
		$this->deletePage( $this->pageToTest->toPageIdentity() );
		$this->deletePage( $this->templatePage->toPageIdentity() );

		$this->runJobs();
	}

	/**
	 * @param Title|null $title
	 * @param bool|null $updateOnly
	 *
	 * @return void
	 * @throws \Exception
	 */
	protected function stabilize( ?Title $title, ?bool $updateOnly = false ) {
		$revision = $this->getServiceContainer()->getRevisionLookup()->getRevisionByPageId( $title->getId() );
		if ( $updateOnly ) {
			$lastStablePoint = $this->lookup->getLastStablePoint( $title );
			$this->stabilizer->updateStablePoint( $lastStablePoint, $this->testUser, 'update' );
		} else {
			$this->stabilizer->addStablePoint( $revision, $this->testUser, 'test' );
		}
	}
}
