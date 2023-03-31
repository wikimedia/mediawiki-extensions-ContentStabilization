<?php

namespace MediaWiki\Extension\ContentStabilization\Tests\Integration;

use User;

/**
 * @group database
 */
class InclusionFreezeTest extends FullIntegrationBase {

	/**
	 * @inheritDoc
	 */
	protected function getInclusionMode(): ?string {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	protected function getEnabledNamespaces() : array {
		return [ NS_MAIN ];
	}

	/**
	 * @inheritDoc
	 */
	protected function shouldAllowFirstUnstable() : bool {
		return false;
	}

	/**
	 *
	 * @return void
	 * @throws \MWException
	 * @throws \PermissionsError
	 * @covers \MediaWiki\Extension\ContentStabilization\Hook\StabilizeContent::onArticleViewHeader
	 * @covers \MediaWiki\Extension\ContentStabilization\Hook\StabilizeContent::onBeforeParserFetchFileAndTitle
	 * @covers \MediaWiki\Extension\ContentStabilization\Hook\StabilizeContent::onBeforeParserFetchTemplateRevisionRecord
	 */
	public function testVisibility() {
		// First draft
		if ( $this->shouldAllowFirstUnstable() ) {
			$this->assertOutputContains( new User(), "V1T1", [], 'Anon user should see first draft' );
		} else {
			$this->assertOutputContains( new User(), null, [], 'Anon user should not see first draft' );
		}

		// while permitted users can see latest
		$this->assertOutputContains( $this->testUser, "V1T1", [], 'Permitted user should see first draft' );

		// Crate first stable
		$this->stabilize( $this->pageToTest );
		// Not permitted users can see stable
		$this->assertOutputContains( new User(), "V1T1", [], 'Anon user should see stable' );
		// same as permitted users
		$this->assertOutputContains( $this->testUser, "V1T1", [], 'Permitted user should see stable' );

		$this->editPage( $this->pageToTest, 'V2{{TestStabilization}}' );
		// Not permitted users can see stable
		$this->assertOutputContains( new User(), "V1T1", [], 'Anon user should see stable, even if there is draft' );
		// even if draft is requested
		$this->assertOutputContains(
			new User(), "V1T1", [ 'stable' => 0 ], 'Anon user should see stable, even draft is requested'
		);
		// permitted users should see stable by default
		$this->assertOutputContains( $this->testUser, "V1T1", [], 'Permitted user should stable by default' );
		// but can also see draft is requested
		$this->assertOutputContains(
			$this->testUser, "V2T1", [ 'stable' => 0 ], 'Permitted user should see draft, if explicitly requested'
		);

		// Update template
		$this->editPage( $this->templatePage, 'T2' );
		// Not permitted users can see stable version of template
		$this->assertOutputContains( new User(), "V1T1", [], 'Anon user should see stable, even if there is draft' );
		// permitted users can see updated
		$this->assertOutputContains(
			$this->testUser, "V2T2", [ 'stable' => 0 ], 'Permitted user should see draft, in page and in resources'
		);

		// Stabilize again
		$this->stabilize( $this->pageToTest );
		// Everyone can see the same version
		$this->assertOutputContains( new User(), "V2T2", [], 'Anon user should see stable' );
		$this->assertOutputContains( $this->testUser, "V2T2", [], 'Permitted user should see stable' );

		// Edit template again - only change in resources
		$this->editPage( $this->templatePage, 'T3' );
		// Not permitted users can see frozen version of template
		$this->assertOutputContains( new User(), "V2T2", [], 'Anon user should not see changes in resources' );
		// permitted users still see stable, by default
		$this->assertOutputContains(
			$this->testUser, "V2T2", [], 'Permitted user should not see changes in resources by default'
		);
		// permitted users can see updated, if requested
		$this->assertOutputContains(
			$this->testUser, "V2T3", [ 'stable' => 0 ], 'Permitted user should see changes in resources'
		);

		$this->stabilize( $this->pageToTest, true );
		$this->assertOutputContains( new User(), "V2T3", [], 'Anon user should see updated stable' );
	}
}
