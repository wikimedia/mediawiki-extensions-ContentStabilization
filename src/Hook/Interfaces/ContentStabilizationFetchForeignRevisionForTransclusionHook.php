<?php

namespace MediaWiki\Extension\ContentStabilization\Hook\Interfaces;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;

interface ContentStabilizationFetchForeignRevisionForTransclusionHook {

	/**
	 * @param array $transclusion
	 * @param RevisionRecord|null &$revision
	 * @param UserIdentity|null $forUser
	 * @param array $options
	 * @return void
	 */
	public function onContentStabilizationFetchForeignRevisionForTransclusion(
		array $transclusion, ?RevisionRecord &$revision, ?UserIdentity $forUser, array $options = []
	): void;
}
