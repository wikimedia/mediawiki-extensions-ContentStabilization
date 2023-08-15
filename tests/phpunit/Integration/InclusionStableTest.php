<?php

namespace MediaWiki\Extension\ContentStabilization\Tests\Integration;

use MWException;
use PermissionsError;
use Title;
use User;

/**
 * @group Database
 */
class InclusionStableTest extends FullIntegrationBase {

	/** @var Title */
	private Title $notEnabledPage;

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		parent::setUp();
		$res = $this->insertPage( 'Help:Inclusion', 'H1' );
		$this->notEnabledPage = $res['title'];
	}

	/**
	 * @inheritDoc
	 */
	protected function getInclusionMode(): ?string {
		return 'stable';
	}

	/**
	 * @inheritDoc
	 */
	protected function getEnabledNamespaces(): array {
		return [ NS_MAIN, NS_TEMPLATE ];
	}

	/**
	 * @inheritDoc
	 */
	protected function shouldAllowFirstUnstable(): bool {
		return false;
	}

	/**
	 *
	 * @return void
	 * @throws MWException
	 * @throws PermissionsError
	 * @covers \MediaWiki\Extension\ContentStabilization\Hook\StabilizeContent::onArticleViewHeader
	 * @covers \MediaWiki\Extension\ContentStabilization\Hook\StabilizeContent::onBeforeParserFetchFileAndTitle
	 * @covers \MediaWiki\Extension\ContentStabilization\Hook\StabilizeContent::onBeforeParserFetchTemplateRevisionRecord
	 */
	public function testVisibility() {
		// Terminology:
		// - page to test - "main" page that we are testing, one that includes a resource
		// - inclusion - page that in included in the "page to test" as a resource

		// Permitted users can see draft version of the page to test and first draft of inclusion
		$this->assertOutputContains( $this->testUser, "V1T1", [], 'Permitted user should see first draft' );

		// Stabilize page to test
		$this->stabilize( $this->pageToTest );

		// Everyone should see first draft of inclusions, as there is no stable there yet
		$this->assertOutputContains( new User(), "V1T1", [], 'Anon should see first draft of inclusion' );
		$this->assertOutputContains( $this->testUser, "V1T1", [], 'Permitted should see first draft of inclusion' );

		// Stabilize inclusion
		$this->stabilize( $this->templatePage );
		// nothing should have changed
		$this->assertOutputContains( new User(), "V1T1", [], 'Anon should see stable version of inclusion' );
		$this->assertOutputContains( $this->testUser, "V1T1", [], 'Permitted should see stable version of inclusion' );

		// Create draft of inclusion
		$this->editPage( $this->templatePage, 'T2' );
		// still nothing should have changed
		$this->assertOutputContains( new User(), "V1T1", [], 'Anon should see stable version of inclusion' );
		$this->assertOutputContains( $this->testUser, "V1T1", [], 'Permitted should see stable version of inclusion' );

		// Create draft of page to test
		$this->editPage( $this->pageToTest, 'V2{{TestStabilization}}' );
		// Anon should see stable version of inclusion and the page
		$this->assertOutputContains(
			new User(), "V1T1", [], 'Anon should see stable version of the page and inclusion'
		);
		// permitted users can see draft of the page to test, but not of the template
		$this->assertOutputContains(
			$this->testUser, "V2T1", [ 'stable' => 0 ],
			'Permitted user should see draft of the current page, but stable version of the inclusion'
		);

		// Stabilize inclusion without touching the page to test
		$this->stabilize( $this->templatePage );
		// Anon should see stable version of the page to test, and stable version of the inclusion at the time
		// when the page to test was stabilized
		$this->assertOutputContains(
			new User(), "V1T2", [], 'Anon should see stable version of the page and inclusion'
		);
		// Permitted users should see stable of the page to test, and stable version of the inclusion, by default
		$this->assertOutputContains(
			$this->testUser, "V1T2", [],
			'Permitted user should see draft of the current page, and stable version of the inclusion'
		);
		// Permitted users should see draft of the page to test, and stable version of the inclusion
		$this->assertOutputContains(
			$this->testUser, "V2T2", [ 'stable' => 0 ],
			'Permitted user should see draft of the current page, and stable version of the inclusion'
		);

		// Stabilize page to test
		$this->stabilize( $this->pageToTest );
		// Now anon should see latest stable of the inclusion
		$this->assertOutputContains(
			new User(), "V2T2", [], 'Anon should see latest stable version of the inclusion'
		);

		// Create draft of inclusion
		$this->editPage( $this->templatePage, 'T3' );
		// everyone should now see last stable of the inclusion
		$this->assertOutputContains(
			new User(), "V2T2", [], 'Anon should see latest stable version of the inclusion'
		);
		$this->assertOutputContains(
			$this->testUser, "V2T2", [ 'stable' => 0 ],
			'Permitted user should see draft of the current page, and stable version of the inclusion'
		);

		// Stable inclusion
		$this->stabilize( $this->templatePage );
		// everyone should now see last stable of the inclusion
		$this->assertOutputContains(
			new User(), "V2T3", [], 'Anon should see latest stable version of the inclusion'
		);
		$this->assertOutputContains(
			$this->testUser, "V2T3", [ 'stable' => 0 ],
			'Permitted user should see draft of the current page, and stable version of the inclusion'
		);
	}

	/**
	 * Test what happens when inclusions are set to "stable",
	 * but page being included does not have stabilization enabled
	 * (Should always see the latest version)
	 *
	 * @return void
	 * @throws MWException
	 * @throws PermissionsError
	 * @covers \MediaWiki\Extension\ContentStabilization\Hook\StabilizeContent::onArticleViewHeader
	 * @covers \MediaWiki\Extension\ContentStabilization\Hook\StabilizeContent::onBeforeParserFetchFileAndTitle
	 * @covers \MediaWiki\Extension\ContentStabilization\Hook\StabilizeContent::onBeforeParserFetchTemplateRevisionRecord
	 */
	public function testNotEnabledNamespace() {
		// Not enabled namespaces behave like freeze
		$this->editPage( $this->pageToTest, 'V1{{Help:Inclusion}}' );
		$this->stabilize( $this->pageToTest );

		$this->editPage( $this->notEnabledPage, 'H2' );

		$this->assertOutputContains(
			$this->testUser, "V1H2", [ 'stable' => 0 ],
			'Permitted user should see draft of the current page, ' .
			'and latest version of the inclusion when stabilization is not enabled'
		);
		$this->assertOutputContains(
			new User(), "V1H1", [],
			'Anon user should see draft of the current page, ' .
			'and latest version of the inclusion when stabilization is not enabled'
		);
	}
}
