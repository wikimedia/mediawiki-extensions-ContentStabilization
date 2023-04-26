<?php

namespace MediaWiki\Extension\ContentStabilization\Tests\Integration;

use MWException;
use PermissionsError;
use User;

/**
 * @group database
 */
class InclusionCurrentTest extends FullIntegrationBase {

	/**
	 * @inheritDoc
	 */
	protected function getInclusionMode(): ?string {
		return 'current';
	}

	/**
	 * @inheritDoc
	 */
	protected function getEnabledNamespaces(): array {
		// In this mode, stable state should not matter
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
		$this->assertOutputContains(
			$this->testUser, "V1T1", [], 'Permitted user should see first draft'
		);

		// Stabilize page to test
		$this->stabilize( $this->pageToTest );

		// Everyone should see latest inclusion
		$this->assertOutputContains(
			new User(), "V1T1", [], 'Anon should see first draft of inclusion'
		);
		$this->assertOutputContains(
			$this->testUser, "V1T1", [], 'Permitted should see first draft of inclusion'
		);

		// New version of inclusion
		$this->editPage( $this->templatePage, 'T2' );
		// Everyone seeing latest
		$this->assertOutputContains(
			new User(), "V1T2", [], 'Anon should see latest version of inclusion'
		);
		$this->assertOutputContains(
			$this->testUser, "V1T2", [], 'Permitted user should should see latest version of inclusion'
		);

		// Stabilize inclusion and create a draft
		$this->stabilize( $this->templatePage );
		$this->editPage( $this->templatePage, 'T3' );

		// Everyone should see the latest draft
		$this->assertOutputContains(
			new User(), "V1T3", [], 'Anon should see latest version of inclusion, even if in draft'
		);
		$this->assertOutputContains(
			$this->testUser, "V1T3", [],
			'Permitted user should should see latest version of inclusion, even if in draft'
		);
	}
}
