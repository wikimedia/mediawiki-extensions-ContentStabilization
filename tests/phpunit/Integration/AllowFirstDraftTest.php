<?php

namespace MediaWiki\Extension\ContentStabilization\Tests\Integration;

/**
 * @group Database
 */
class AllowFirstDraftTest extends InclusionFreezeTest {

	/**
	 * @inheritDoc
	 */
	protected function shouldAllowFirstUnstable(): bool {
		return true;
	}
}
