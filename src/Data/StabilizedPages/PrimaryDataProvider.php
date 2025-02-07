<?php

namespace MediaWiki\Extension\ContentStabilization\Data\StabilizedPages;

use MediaWiki\Extension\ContentStabilization\StableView;
use MediaWiki\Language\Language;
use MWStake\MediaWiki\Component\DataStore\Filter;
use MWStake\MediaWiki\Component\DataStore\PrimaryDatabaseDataProvider;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use MWStake\MediaWiki\Component\DataStore\Schema;
use stdClass;
use Wikimedia\Rdbms\IDatabase;

class PrimaryDataProvider extends PrimaryDatabaseDataProvider {

	/** @var array */
	private $fields = [
		Record::PAGE_ID => 'page_id',
		Record::PAGE_TITLE => 'page_title',
		Record::PAGE_NAMESPACE => 'page_namespace',
		Record::STABLE_COUNT => 'stable_count',
	];

	/** @var array */
	private $enabledNamespaces = [];

	/** @var array */
	private $namespaceTexts = [];

	/** @var Language */
	private $language;

	/**
	 * @param IDatabase $db
	 * @param Schema $schema
	 * @param array $enabledNamespaces
	 * @param Language $language
	 */
	public function __construct( IDatabase $db, Schema $schema, array $enabledNamespaces, Language $language ) {
		parent::__construct( $db, $schema );
		$this->enabledNamespaces = $enabledNamespaces;
		$this->language = $language;
	}

	/**
	 * @param ReaderParams $params
	 *
	 * @return array
	 */
	public function makeData( $params ) {
		$this->data = [];
		$conds = $this->makePreFilterConds( $params );
		$conds[] = 'page_namespace IN (' . $this->db->makeList( $this->enabledNamespaces ) . ')';
		$options = $this->makePreOptionConds( $params );
		$options['GROUP BY'] = [ 'page_id' ];

		$res = $this->db->select(
			[ 'page', 'stable_points', 'user' ],
			[
				'page_id', 'page_title', 'page_namespace', 'page_latest',
				'sp_page', 'MAX( sp_revision ) as last_stable',
				'MAX( sp_time ) as last_stable_ts', 'COUNT( sp_revision ) as stable_count',
				'user_name as last_approver'
			],
			$conds,
			__METHOD__,
			$options,
			[
				'stable_points' => [ 'LEFT OUTER JOIN', 'page_id=sp_page' ],
				'user' => [ 'LEFT OUTER JOIN', 'sp_user=user_id' ]
			]
		);
		foreach ( $res as $row ) {
			$this->appendRowToData( $row );
		}
		return $this->data;
	}

	/**
	 * @param array &$conds
	 * @param Filter $filter
	 *
	 * @return void
	 */
	protected function appendPreFilterCond( &$conds, Filter $filter ) {
		if ( $filter->getField() === Record::LAST_APPROVER ) {
			return;
		}
		if ( !isset( $this->fields[$filter->getField()] ) ) {
			parent::appendPreFilterCond( $conds, $filter );
			return;
		}
		$filterClass = get_class( $filter );
		$filter = new $filterClass( [
			'field' => $this->fields[$filter->getField()],
			'comparison' => $filter->getComparison(),
			'value' => $filter->getValue()
		] );
		parent::appendPreFilterCond( $conds, $filter );
	}

	/**
	 * @param ReaderParams $params
	 *
	 * @return array
	 */
	protected function makePreOptionConds( ReaderParams $params ) {
		$conds = $this->getDefaultOptions();
		$fields = array_values( $this->schema->getSortableFields() );
		foreach ( $params->getSort() as $sort ) {
			if ( !in_array( $sort->getProperty(), $fields ) ) {
				continue;
			}
			if ( !isset( $this->fields[$sort->getProperty()] ) ) {
				continue;
			}
			if ( !isset( $conds['ORDER BY'] ) ) {
				$conds['ORDER BY'] = "";
			} else {
				$conds['ORDER BY'] .= ",";
			}
			$sortField = $this->fields[$sort->getProperty()];
			$conds['ORDER BY'] .= "$sortField {$sort->getDirection()}";
		}
		return $conds;
	}

	/**
	 * @return string
	 */
	protected function getTableNames() {
		// Not used
		return '';
	}

	/**
	 * @param stdClass $row
	 *
	 * @return void
	 */
	protected function appendRowToData( stdClass $row ) {
		$state = $this->getState( $row );
		$this->data[] = new Record( (object)[
			Record::PAGE_ID => $row->page_id,
			Record::PAGE_TITLE => $row->page_title,
			Record::PAGE_NAMESPACE => (int)$row->page_namespace,
			Record::STABLE_COUNT => (int)$row->stable_count,
			Record::LAST_STABLE_TS => $row->last_stable_ts,
			Record::LAST_STABLE => (int)$row->last_stable,
			Record::LAST_APPROVER => $row->last_approver,
			Record::LAST_COMMENT => null,
			Record::PAGE_DISPLAY_TEXT => $this->getDisplayText( $row ),
			Record::STATUS => $state,
		] );
	}

	/**
	 * @param stdClass $row
	 *
	 * @return string
	 */
	private function getState( \stdClass $row ): string {
		$pageLatest = (int)$row->page_latest;
		$lastStable = (int)$row->last_stable;

		if ( !$lastStable ) {
			return StableView::STATE_FIRST_UNSTABLE;
		}
		if ( $pageLatest === $lastStable ) {
			return StableView::STATE_STABLE;
		}
		return StableView::STATE_UNSTABLE;
	}

	/**
	 * @param stdClass $row
	 *
	 * @return string
	 */
	private function getDisplayText( stdClass $row ): string {
		$ns = (int)$row->page_namespace;
		if ( $ns === NS_MAIN ) {
			return $row->page_title;
		}
		if ( !isset( $this->namespaceTexts[$ns] ) ) {
			$this->namespaceTexts[$ns] = $this->language->getNsText( $ns );
		}
		$nsText = $this->namespaceTexts[$ns];
		return "$nsText:{$row->page_title}";
	}
}
