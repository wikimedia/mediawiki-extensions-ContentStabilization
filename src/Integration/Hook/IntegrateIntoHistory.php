<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use Language;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Hook\PageHistoryLineEndingHook;
use MediaWiki\Page\Hook\ImagePageFileHistoryLineHook;
use MediaWiki\User\UserIdentity;
use Message;
use Title;
use TitleFactory;

class IntegrateIntoHistory implements PageHistoryLineEndingHook, BeforeInitializeHook, ImagePageFileHistoryLineHook {

	/** @var StabilizationLookup */
	private $lookup;

	/** @var Language */
	private $language;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var UserIdentity|null */
	private $user;

	/**
	 * @param StabilizationLookup $lookup
	 * @param Language $language
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		StabilizationLookup $lookup, Language $language, TitleFactory $titleFactory
	) {
		$this->lookup = $lookup;
		$this->language = $language;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function onPageHistoryLineEnding( $historyAction, &$row, &$s, &$classes, &$attribs ) {
		if ( !$this->lookup->isStabilizationEnabled( $historyAction->getTitle()->toPageIdentity() ) ) {
			return;
		}
		$point = $this->lookup->getStablePointForRevisionId( (int)$row->rev_id );
		if ( !$point ) {
			$classes[] = 'content-stabilization-not-stable';
			$title = $this->titleFactory->newFromID( $row->rev_page );
			if ( !$title ) {
				// Something wrong
				return;
			}
			if ( !$this->lookup->canUserSeeUnstable( $this->user ) && !$this->showFirstUnstable( $title ) ) {
				$classes[] = 'content-stabilization-hidden';
			}
			return;
		}
		$classes[] = 'content-stabilization-stable';
		$stabilizationDataString = $this->generateStabilizationData( $point );
		$s .= $stabilizationDataString;
	}

	/**
	 * @param StablePoint $point
	 *
	 * @return string
	 */
	private function generateStabilizationData( StablePoint $point ): string {
		$actor = $point->getApprover()->getName();
		$timestamp = $this->language->userTimeAndDate( $point->getTime()->format( 'YmdHis' ), $this->user );
		$comment = $point->getComment();
		if ( !$comment ) {
			$comment = '-';
		}
		$msg = Message::newFromKey( 'content-stabilization-stable-data', $actor, $timestamp, $comment );

		return "<div class='content-stabilization-stable-data'>{$msg->parse()}</div>";
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWiki ) {
		$this->user = $user;
	}

	/**
	 * @inheritDoc
	 */
	public function onImagePageFileHistoryLine( $imageHistoryList, $file, &$line, &$css ) {
		$point = $this->lookup->getStablePointForFile( $file );
		if ( !$point ) {
			if (
				$this->lookup->canUserSeeUnstable( $this->user ) ||
				$this->showFirstUnstable( $file->getTitle() )
			) {
				$css .= 'content-stabilization-not-stable';
				return;
			}
			$line = '';
			return;
		}
		$css .= 'content-stabilization-stable';
	}

	/**
	 * @param Title $title
	 *
	 * @return bool
	 */
	private function showFirstUnstable( Title $title ): bool {
		$isFirstUnstable = !$this->lookup->hasStable( $title );
		return $isFirstUnstable && $this->lookup->isFirstUnstableAllowed();
	}
}
