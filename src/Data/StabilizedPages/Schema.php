<?php

namespace MediaWiki\Extension\ContentStabilization\Data\StabilizedPages;

use MWStake\MediaWiki\Component\DataStore\FieldType;
use MWStake\MediaWiki\Component\DataStore\Schema as BaseSchema;

class Schema extends BaseSchema {
	public function __construct() {
		parent::__construct( [
			Record::PAGE_ID => [
				self::FILTERABLE => false,
				self::SORTABLE => true,
				self::TYPE => FieldType::INT
			],
			Record::PAGE_TITLE => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			Record::PAGE_NAMESPACE => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::INT
			],
			Record::PAGE_DISPLAY_TEXT => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::STRING
			],
			Record::PAGE_LINK => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::STRING
			],
			Record::STABLE_COUNT => [
				self::FILTERABLE => false,
				self::SORTABLE => true,
				self::TYPE => FieldType::INT
			],
			Record::STATUS => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			Record::HAS_CHANGED_INCLUSIONS => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::BOOLEAN
			],
			Record::LAST_STABLE => [
				self::FILTERABLE => false,
				self::SORTABLE => true,
				self::TYPE => FieldType::DATE
			],
			Record::LAST_STABLE_TS => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::DATE
			],
			Record::LAST_APPROVER => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			Record::LAST_COMMENT => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::STRING
			]
		] );
	}
}
