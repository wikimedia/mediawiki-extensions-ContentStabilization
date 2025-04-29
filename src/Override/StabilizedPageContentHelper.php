<?php

namespace MediaWiki\Extension\ContentStabilization\Override;

use MediaWiki\ChangeTags\ChangeTagsStore;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Page\PageLookup;
use MediaWiki\Rest\Handler\Helper\PageContentHelper;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleFormatter;
use Wikimedia\Rdbms\IConnectionProvider;

class StabilizedPageContentHelper extends PageContentHelper {

	/**
	 * @var StabilizationLookup
	 */
	private StabilizationLookup $stabilizationLookup;

	/**
	 * @param ServiceOptions $options
	 * @param RevisionLookup $revisionLookup
	 * @param TitleFormatter $titleFormatter
	 * @param PageLookup $pageLookup
	 * @param TitleFactory $titleFactory
	 * @param IConnectionProvider $connectionProvider
	 * @param ChangeTagsStore $changeTagStore
	 * @param StabilizationLookup $stabilizationLookup
	 */
	public function __construct(
		ServiceOptions $options, RevisionLookup $revisionLookup, TitleFormatter $titleFormatter,
		PageLookup $pageLookup, TitleFactory $titleFactory, IConnectionProvider $connectionProvider,
		ChangeTagsStore $changeTagStore, StabilizationLookup $stabilizationLookup
	) {
		parent::__construct(
			$options, $revisionLookup, $titleFormatter, $pageLookup, $titleFactory,
			$connectionProvider, $changeTagStore
		);
		$this->stabilizationLookup = $stabilizationLookup;
	}

	/**
	 * @return RevisionRecord|null
	 */
	public function getTargetRevision(): ?RevisionRecord {
		if ( !$this->getPage() ) {
			return parent::getTargetRevision();
		}
		if ( !$this->stabilizationLookup->isStabilizationEnabled( $this->getPage() ) ) {
			return parent::getTargetRevision();
		}
		if ( $this->stabilizationLookup->canUserSeeUnstable( $this->authority->getUser() ) ) {
			return parent::getTargetRevision();
		}
		$parent = parent::getTargetRevision();
		if ( $this->stabilizationLookup->isStableRevision( $parent ) ) {
			return $parent;
		}
		return $this->stabilizationLookup->getLastStablePoint( $this->getPage() )?->getRevision();
	}
}
