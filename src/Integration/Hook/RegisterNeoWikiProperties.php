<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use BlueSpice\GalaxyDistributionConnector\NeoWiki\PropertyType\UserProperty;
use BlueSpice\GalaxyDistributionConnector\NeoWiki\Util\PropertyWithLabel;
use MediaWiki\Extension\ContentStabilization\Hook\Interfaces\ContentStabilizationStablePointAddedHook;
use MediaWiki\Extension\ContentStabilization\Hook\Interfaces\ContentStabilizationStablePointRemovedHook;
use MediaWiki\Extension\ContentStabilization\Integration\NeoWiki\StabilizationProperties;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\BooleanProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateTimeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
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
			->newPageRebuilder()
			->rebuild( $title );
	}

	/**
	 * @param array &$propertyDefinitions
	 * @return void
	 */
	public function onBlueSpiceGalaxyConnectorNeoWikiPagePropertyDefinitions( array &$propertyDefinitions ): void {
		$propertyDefinitions['qm_state'] = new PropertyWithLabel(
			label: Message::newFromKey( 'contentstabilization-overview-header-status' )->text(),
			definition: new TextProperty(
				core: new PropertyCore(
					description: Message::newFromKey( 'contentstabilization-overview-header-status' )->text(),
					required: true,
					default: ''
				),
				multiple: false, uniqueItems: false,
				minLength: 0,
				maxLength: null
			)
		);
		$propertyDefinitions['qm_approved_by'] = new PropertyWithLabel(
			label: Message::newFromKey( 'contentstabilization-versionhistory-grid-header-approver' )->text(),
			definition: new UserProperty(
				core: new PropertyCore(
					description: Message::newFromKey(
						'contentstabilization-versionhistory-grid-header-approver'
					)->text(),
					required: true,
					default: ''
				)
			)
		);
		$propertyDefinitions['qm_approved_at'] = new PropertyWithLabel(
			label: Message::newFromKey( 'contentstabilization-versionhistory-grid-header-approval-date' )->text(),
			definition: new DateTimeProperty(
				core: new PropertyCore(
					description: Message::newFromKey(
						'contentstabilization-versionhistory-grid-header-approval-date'
					)->text(),
					required: true,
					default: null
				),
				minimum: null, maximum: null
			)
		);
		$propertyDefinitions['qm_has_draft'] = new PropertyWithLabel(
			label: Message::newFromKey( 'contentstabilization-neowiki-property-has-draft' )->text(),
			definition: new BooleanProperty(
				core: new PropertyCore(
					description: Message::newFromKey(
						'contentstabilization-neowiki-property-has-draft'
					)->text(),
					required: true,
					default: true
				)
			)
		);
	}
}
