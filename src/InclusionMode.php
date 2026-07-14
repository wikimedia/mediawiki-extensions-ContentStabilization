<?php

namespace MediaWiki\Extension\ContentStabilization;

use MediaWiki\Revision\RevisionRecord;

interface InclusionMode {

	/**
	 * @param array $inclusion
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return array
	 */
	public function stabilizeInclusions( array $inclusion, RevisionRecord $revisionRecord ): array;

	/**
	 * Whether the inclusion mode can be out of sync with the current revision
	 *
	 * @param RevisionRecord|null $revisionToCheckFor
	 *
	 * @return bool
	 */
	public function canBeOutOfSync( ?RevisionRecord $revisionToCheckFor = null ): bool;
}
