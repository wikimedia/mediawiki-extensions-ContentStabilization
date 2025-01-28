<?php

namespace MediaWiki\Extension\ContentStabilization\Migration;

use MediaWiki\Status\Status;
use Wikimedia\Rdbms\ILoadBalancer;

class LogComments {

	/** @var ILoadBalancer */
	private $loadBalancer;

	/**
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @return Status
	 */
	public function migrate() {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );

		$migrated = 0;
		$batchSize = 250;
		$db->startAtomic( __METHOD__ );
		do {
			$res = $db->select(
				[ 'l' => 'logging', 'c' => 'comment' ],
				[
					'l.log_page', 'l.log_comment_id', 'c.comment_text', 'l.log_params'
				],
				[ 'log_type' => 'review', 'log_action LIKE \'approve%\'', 'log_comment_id > 0' ],
				__METHOD__,
				[ "LIMIT" => $batchSize, "OFFSET" => $migrated ],
				[
					'c' => [ 'INNER JOIN', [ 'l.log_comment_id = c.comment_id' ] ]
				]
			);

			$numRows = $res->count();
			if ( $numRows ) {
				foreach ( $res as $row ) {
					if ( !empty( $row->sp_comment ) ) {
						// Comment already exists, do not overwrite
						continue;
					}
					$params = unserialize( $row->log_params );
					if ( !is_array( $params ) ) {
						// B/C for old logs that dont use serialization
						$params = explode( "\n", $row->log_params );
						$params = array_map( 'trim', $params );
					}
					if ( count( $params ) < 3 ) {
						// This should never happen
						continue;
					}

					$revId = (int)$params[0];
					$comment = $row->comment_text;
					$db->update(
						'stable_points',
						[ 'sp_comment' => $comment ],
						[ 'sp_revision' => $revId, 'sp_page' => $row->log_page ],
						__METHOD__
					);
				}
			}
			$migrated += $numRows;
		} while ( $numRows >= $batchSize );
		$db->endAtomic( __METHOD__ );

		return Status::newGood( [ 'migrate_comments' => $migrated ] );
	}
}
