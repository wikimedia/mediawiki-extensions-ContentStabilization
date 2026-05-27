<?php

namespace MediaWiki\Extension\ContentStabilization\Data\StabilizedPages;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\DataStore\IContinueAwareRecord;
use MWStake\MediaWiki\Component\DataStore\Record as BaseRecord;

class Record extends BaseRecord implements IContinueAwareRecord {
	public const PAGE_ID = 'page_id';
	public const PAGE_NAMESPACE = 'page_namespace';
	public const PAGE_TITLE = 'page_title';
	public const PAGE_DISPLAY_TEXT = 'page_display_text';
	public const PAGE_LINK = 'page_link';
	public const STABLE_COUNT = 'stable_count';
	public const STATUS = 'status';
	public const LAST_STABLE = 'last_stable';
	public const LAST_STABLE_TS = 'last_stable_ts';
	public const LAST_APPROVER = 'last_approver';
	public const LAST_COMMENT = 'last_comment';
	public const HAS_CHANGED_INCLUSIONS = 'has_changed_inclusions';

	/**
	 * @param TitleFactory $titleFactory
	 * @return Title|null
	 */
	public function getTitle( TitleFactory $titleFactory ): ?Title {
		return $titleFactory->makeTitleSafe(
			$this->get( self::PAGE_NAMESPACE ), $this->get( self::PAGE_TITLE )
		);
	}

	/**
	 * @return array
	 */
	public function getContinueValue(): array {
		return [ $this->get( self::PAGE_NAMESPACE ), $this->get( self::PAGE_TITLE ) ];
	}

	/**
	 * @param array $continueValue
	 * @return bool
	 */
	public function matchesContinueValue( array $continueValue ): bool {
		return count( $continueValue ) === 2 &&
			$this->get( self::PAGE_NAMESPACE ) === $continueValue[0] &&
			$this->get( self::PAGE_TITLE ) === $continueValue[1];
	}
}
