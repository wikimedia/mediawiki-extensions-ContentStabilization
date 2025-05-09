<?php

//phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use File;
use MediaWiki\Config\Config;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StableFilePoint;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;

class StabilizeSMWProperties {
	/** @var StabilizationLookup */
	private $lookup;
	/** @var Config */
	private $csConfig;

	/** @var StablePoint|null */
	private $stable = null;

	/**
	 * @param StabilizationLookup $lookup
	 * @param Config $csConfig
	 */
	public function __construct( StabilizationLookup $lookup, Config $csConfig ) {
		$this->lookup = $lookup;
		$this->csConfig = $csConfig;
	}

	/**
	 * @param Title $title
	 * @param int &$revId
	 *
	 * @return void
	 */
	public function onSMW__RevisionGuard__ChangeRevisionID( Title $title, &$revId ) {
		if ( !$this->shouldStabilize( $title ) ) {
			return;
		}
		$this->assertStable( $title );
		if ( !$this->stable ) {
			return;
		}
		// No way to return NO revision, either we replace it, or it will use the original
		$revId = $this->stable->getRevision()->getId();
	}

	/**
	 * @param Title $title
	 * @param int $revId
	 *
	 * @return bool
	 */
	public function onSMW__RevisionGuard__IsApprovedRevision( Title $title, $revId ): bool {
		if ( !$this->shouldStabilize( $title ) ) {
			return true;
		}
		$this->assertStable( $title, $revId );
		if ( !$this->stable ) {
			// Allow properties from first unstable, if so configured.
			return $this->lookup->isFirstUnstableAllowed();
		}
		return $this->stable->getRevision()->getId() === (int)$revId;
	}

	/**
	 * @param Title $title
	 * @param ?File &$file
	 *
	 * @return void
	 */
	public function onSMW__RevisionGuard__ChangeFile( Title $title, ?File &$file ) {
		if ( !$this->shouldStabilize( $title ) ) {
			return;
		}
		$this->assertStable( $title );
		if ( !$this->stable || !( $this->stable instanceof StableFilePoint ) ) {
			return;
		}
		$file = $this->stable->getFile();
	}

	/**
	 * @param Title $title
	 * @param RevisionRecord|null &$revision
	 *
	 * @return void
	 */
	public function onSMW__RevisionGuard__ChangeRevision( Title $title, ?RevisionRecord &$revision ) {
		if ( !$this->shouldStabilize( $title ) ) {
			return;
		}
		$this->assertStable( $title );
		if ( !$this->stable ) {
			return;
		}
		// No way to return NO revision, either we replace it, or it will use the original
		$revision = $this->stable->getRevision();
	}

	/**
	 * @param Title $title
	 * @param int|null $revId
	 *
	 * @return void
	 */
	private function assertStable( Title $title, ?int $revId = null ) {
		$stable = $this->lookup->getLastRawStablePoint( $title->toPageIdentity(), $revId );
		if ( !$stable ) {
			$this->stable = null;
			return;
		}
		$this->stable = $stable;
	}

	/**
	 * @param Title $title
	 *
	 * @return bool
	 */
	private function shouldStabilize( Title $title ): bool {
		return (bool)$this->csConfig->get( 'StabilizeSMWProperties' ) &&
			$this->lookup->isStabilizationEnabled( $title->toPageIdentity() );
	}
}
