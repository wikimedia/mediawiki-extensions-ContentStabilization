<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use File;
use IContextSource;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\DrawioEditor\Hook\DrawioGetFileHook;
use MediaWiki\Hook\BeforeInitializeHook;
use RepoGroup;
use User;

class StabilizeDrawioFiles implements DrawioGetFileHook, BeforeInitializeHook {

	/** @var StabilizationLookup */
	private $lookup;

	/** @var RepoGroup */
	private $repoGroup;

	/** @var IContextSource */
	private $context;

	private $doNotStabilize = false;

	/**
	 * @param StabilizationLookup $lookup
	 * @param RepoGroup $repoGroup
	 */
	public function __construct( StabilizationLookup $lookup, RepoGroup $repoGroup ) {
		$this->lookup = $lookup;
		$this->repoGroup = $repoGroup;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWiki ) {
		$this->context = $output->getContext();
	}

	/**
	 * @inheritDoc
	 */
	public function onDrawioGetFile(
		File &$file, &$latestIsStable, User $user, bool &$isNotApproved, File &$displayFile
	) {
		if ( !$this->context ) {
			return;
		}
		if ( !$this->lookup->isStabilizationEnabled( $this->context->getTitle() ) ) {
			return;
		}
		if ( $this->doNotStabilize ) {
			return;
		}
		// This is an edge case of stabilizing a transclusion that happens though a parser hook
		// In order to determine the view for the current page, we need to parse it to get the latest transclusions
		// so that we can compare to frozen transclusions and determine the state of the page
		// However, doing this will process parser functions and therefore call this hook again,
		// causing an infinite loop. So we need to prevent by "turning the handler off" while we are
		// determining the view
		$this->doNotStabilize = true;
		$view = $this->lookup->getStableViewFromContext( $this->context );
		$this->doNotStabilize = false;
		if ( !$view ) {
			$isNotApproved = true;
			return;
		}
		foreach ( $view->getInclusions()['images'] as $image ) {
			if ( $image['name'] === $file->getName() ) {
				if ( $image['revision'] === 0 ) {
					$isNotApproved = true;
					return;
				}
				$file = $this->repoGroup->findFile( $file->getTitle(), [ 'time' => $image['timestamp'] ] );
				$displayFile = $file;
				return;
			}
		}
	}

}
