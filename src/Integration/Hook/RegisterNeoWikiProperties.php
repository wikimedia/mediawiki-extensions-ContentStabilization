<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use MediaWiki\Extension\ContentStabilization\Hook\Interfaces\ContentStabilizationStablePointAddedHook;
use MediaWiki\Extension\ContentStabilization\Hook\Interfaces\ContentStabilizationStablePointRemovedHook;
use MediaWiki\Extension\ContentStabilization\Integration\NeoWiki\StabilizationProperties;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiRegistrar;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class RegisterNeoWikiProperties implements
	ContentStabilizationStablePointAddedHook,
	ContentStabilizationStablePointRemovedHook
{

	/**
	 * @param TitleFactory $titleFactory
	 * @param StabilizationLookup $stabilizationLookup
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly StabilizationLookup $stabilizationLookup,
		private readonly RevisionLookup $revisionLookup
	) {
	}

	/**
	 * @param NeoWikiRegistrar $registrar
	 * @return void
	 */
	public function onNeoWikiRegistration( NeoWikiRegistrar $registrar ): void {
		$registrar->addPagePropertyProvider(
			new StabilizationProperties( $this->titleFactory, $this->stabilizationLookup, $this->revisionLookup )
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onContentStabilizationStablePointAdded( StablePoint $stablePoint ): void {
		$this->tryUpdateNeoWikiProperties( $stablePoint->getRevision() );
	}

	/**
	 * @inheritDoc
	 */
	public function onContentStabilizationStablePointRemoved( StablePoint $removedPoint, Authority $remover ): void {
		$this->tryUpdateNeoWikiProperties( $removedPoint->getRevision() );
	}

	/**
	 * @param RevisionRecord $revision
	 * @return void
	 */
	private function tryUpdateNeoWikiProperties( RevisionRecord $revision ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'NeoWiki' ) ) {
			return;
		}
		$title = $this->titleFactory->castFromPageIdentity( $revision->getPage() );
		if ( !$title ) {
			return;
		}
		NeoWikiExtension::getInstance()
			->newSubjectPageRebuilder()
			->rebuild( $title );
	}
}
