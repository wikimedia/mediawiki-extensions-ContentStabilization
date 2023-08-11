<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\SmartList;

use BlueSpice\SmartList\Mode\GenericSmartlistMode;
use BsInvalidNamespaceException;

/**
 * "Recently stabilized pages" smartlist mode
 */
class StablePages extends GenericSmartlistMode {

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'stablepages';
	}

	/**
	 * @inheritDoc
	 */
	protected function getItems( $args, $context ): array {
		$dbr = $this->services->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$conditions = [];

		switch ( $args['period'] ) {
			case 'month':
				$minTimestamp = $dbr->timestamp( time() - 30 * 24 * 60 * 60 );
				break;
			case 'week':
				$minTimestamp = $dbr->timestamp( time() - 7 * 24 * 60 * 60 );
				break;
			case 'day':
				$minTimestamp = $dbr->timestamp( time() - 24 * 60 * 60 );
				break;
			default:
				break;
		}

		try {
			$namespaceIds = $this->makeNamespaceArrayDiff( $args );
			$conditions[] = 'page_namespace IN (' . implode( ',', $namespaceIds ) . ')';
		} catch ( BsInvalidNamespaceException $ex ) {
			$sInvalidNamespaces = implode( ', ', $ex->getListOfInvalidNamespaces() );

			return [ 'error' =>
				$context->msg( 'bs-smartlist-invalid-namespaces' )
					->numParams( count( $ex->getListOfInvalidNamespaces() ) )
					->params( $sInvalidNamespaces )
					->text()
			];
		}

		$this->makeCategoriesFilterCondition( $conditions, 'page_id', $args );

		if ( !empty( $args['period'] ) && $args['period'] !== '-' ) {
			$conditions[] = "sp_time > '" . $minTimestamp . "'";
		}

		switch ( $args['sort'] ) {
			case 'title':
				$orderSQL = 'page_title';
				break;
			default:
				// ORDER BY MAX() - this one was tricky. It makes sure, only the
				// changes with the maximum date are selected.
				$orderSQL = 'MAX(sp_time)';
				break;
		}

		switch ( $args['order'] ) {
			case 'ASC':
				$orderSQL .= ' ASC';
				break;
			default:
				$orderSQL .= ' DESC';
				break;
		}

		$res = $dbr->select(
			[ 'stable_points', 'page' ],
			[ 'page_id', 'page_title as title', 'page_namespace as namespace' ],
			$conditions,
			__METHOD__,
			[
				'ORDER BY' => $orderSQL,
				'GROUP BY' => 'page_title, page_namespace'
			],
			[ 'page' => [ 'INNER JOIN', 'sp_page = page_id' ] ]
		);

		$count = 0;
		$objectList = [];
		foreach ( $res as $row ) {
			if ( $count == $args['count'] ) {
				break;
			}

			$title = $this->services->getTitleFactory()->makeTitleSafe( $row->namespace, $row->title );
			$userCanRead = $this->services->getPermissionManager()->quickUserCan(
				'read',
				$context->getUser(),
				$title
			);
			if ( !$title || !$userCanRead ) {
				continue;
			}

			$objectList[] = $row;
			$count++;
		}

		return $objectList;
	}
}
