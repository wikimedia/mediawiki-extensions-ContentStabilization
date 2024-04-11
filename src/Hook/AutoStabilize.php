<?php

namespace MediaWiki\Extension\ContentStabilization\Hook;

use MediaWiki\Extension\ContentStabilization\ContentStabilizer;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use Psr\Log\LoggerInterface;
use RequestContext;
use Throwable;

class AutoStabilize implements PageSaveCompleteHook {

	/**
	 * @var ContentStabilizer
	 */
	private $stabilizer;

	/** @var StabilizationLookup */
	private $lookup;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param ContentStabilizer $stabilizer
	 * @param StabilizationLookup $lookup
	 * @param LoggerInterface $logger
	 */
	public function __construct( ContentStabilizer $stabilizer, StabilizationLookup $lookup, LoggerInterface $logger ) {
		$this->stabilizer = $stabilizer;
		$this->lookup = $lookup;
		$this->logger = $logger;
	}

	/**
	 * @inheritDoc
	 */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		if ( !$this->lookup->isStabilizationEnabled( $wikiPage->getTitle() ) ) {
			return;
		}
		$request = RequestContext::getMain()->getRequest();
		$shouldStabilize = $request->getCheck( 'wpStabilize' );
		if ( !$shouldStabilize ) {
			return;
		}
		try {
			$this->stabilizer->addStablePoint(
				$revisionRecord, RequestContext::getMain()->getUser(), '', ContentStabilizer::VALIDATION_IGNORE_CURRENT
			);
		} catch ( Throwable $ex ) {
			$this->logger->error( 'Failed to auto-stabilize page: {exception}', [
				'exception' => $ex
			] );
			// Ignore - do not break a save
		}
	}
}
