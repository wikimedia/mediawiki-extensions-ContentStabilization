<?php

namespace MediaWiki\Extension\ContentStabilization\Tests\Integration;

use Exception;
use MediaWiki\User\User;
use PermissionsError;

/**
 * @group Database
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
		return [ NS_MAIN ];
	}

	/**
	 * @inheritDoc
	 */
	protected function shouldAllowFirstUnstable(): bool {
		return true;
	}

	/**
	 * @return void
	 * @throws Exception
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
	}
}
