<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Hook\ImgAuthBeforeStreamHook;
use RepoGroup;
use RequestContext;

class StabilizeImgAuth implements ImgAuthBeforeStreamHook {

	/** @var StabilizationLookup */
	private $lookup;
	/** @var RepoGroup */
	private $repoGroup;

	/**
	 * @param StabilizationLookup $lookup
	 * @param RepoGroup $repoGroup
	 */
	public function __construct(
		StabilizationLookup $lookup, RepoGroup $repoGroup
	) {
		$this->lookup = $lookup;
		$this->repoGroup = $repoGroup;
	}

	/**
	 * @inheritDoc
	 */
	public function onImgAuthBeforeStream( &$title, &$path, &$name, &$result ) {
		if ( !$this->lookup->isStabilizationEnabled( $title ) ) {
			return true;
		}
		if ( $this->lookup->canUserSeeUnstable( RequestContext::getMain()->getUser() ) ) {
			return true;
		}

		$repo = $this->repoGroup->getLocalRepo();
		$bits = explode( '!', $name, 2 );
		$archive = substr( $path, 0, 9 ) === '/archive/'
			|| substr( $path, 0, 15 ) === '/thumb/archive/';

		if ( $archive && count( $bits ) === 2 ) {
			$file = $repo->newFromArchiveName( $bits[1], $name );
		} else {
			$file = $repo->newFile( $name );
		}
		if ( !$file ) {
			return true;
		}
		$file->load();
		if ( !$file->getTimestamp() ) {
			return true;
		}

		$hasStable = $this->lookup->hasStable( $file->getTitle()->toPageIdentity() );
		if ( !$hasStable && $this->lookup->isFirstUnstableAllowed() ) {
			return true;
		}
		// Last stable point (latest, or latest before specified revision)
		$stable = $this->lookup->getStablePointForFile( $file );
		if ( $stable ) {
			return true;
		}
		$result = [ 'img-auth-accessdenied', 'img-auth-badtitle', $name ];
		return false;
	}
}
