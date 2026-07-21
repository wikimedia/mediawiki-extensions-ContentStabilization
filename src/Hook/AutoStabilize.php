<?php

namespace MediaWiki\Extension\ContentStabilization\Hook;

use MediaWiki\Content\Hook\ContentAlterParserOutputHook;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ContentStabilization\ContentStabilizer;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use Psr\Log\LoggerInterface;
use Throwable;

class AutoStabilize implements PageSaveCompleteHook, ContentAlterParserOutputHook {

	/**
	 * @param ContentStabilizer $stabilizer
	 * @param StabilizationLookup $lookup
	 * @param RevisionStore $revisionStore
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		private readonly ContentStabilizer $stabilizer,
		private readonly StabilizationLookup $lookup,
		private readonly RevisionStore $revisionStore,
		private readonly LoggerInterface $logger
	) {
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
				$revisionRecord,
				RequestContext::getMain()->getUser(),
				'',
				ContentStabilizer::VALIDATION_IGNORE_CURRENT
			);
		} catch ( Throwable $ex ) {
			$this->logger->error( 'Failed to auto-stabilize page: {exception}', [
				'exception' => $ex
			] );
			// Ignore - do not break a save
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onContentAlterParserOutput( $content, $title, $parserOutput ) {
		if ( !$this->lookup->isStabilizationEnabled( $title ) ) {
			return;
		}

		$context = RequestContext::getMain();
		if ( $context->getRequest()->getText( 'action' ) !== "move" ) {
			return;
		}

		$lastStablePoint = $this->lookup->getLastStablePoint( $title );
		if ( !$lastStablePoint ) {
			return;
		}

		$selected = $this->revisionStore->getRevisionByTitle( $title );
		if ( !$selected ) {
			return;
		}

		$isStable = $this->lookup->isStableRevision( $selected );
		if ( $isStable ) {
			return;
		}

		$previous = $this->revisionStore->getPreviousRevision( $selected );
		if ( !$previous ) {
			return;
		}

		$isPreviousStable = $this->lookup->isStableRevision( $previous );
		if ( !$isPreviousStable ) {
			return;
		}

		$this->stabilizer->moveStablePointUnsafe(
			$lastStablePoint,
			$selected,
			$lastStablePoint->getApprover(),
			$lastStablePoint->getComment()
		);
	}
}
