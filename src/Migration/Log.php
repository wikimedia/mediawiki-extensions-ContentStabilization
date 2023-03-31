<?php

namespace MediaWiki\Extension\ContentStabilization\Migration;

use Status;
use Wikimedia\Rdbms\ILoadBalancer;

class Log {

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
		$res = $db->select(
			'logging',
			[ 'log_timestamp', 'log_actor', 'log_namespace', 'log_title', 'log_page', 'log_comment_id' ],
			[ 'log_type' => 'review', 'log_action LIKE \'approve%\'' ],
			__METHOD__
		);

		$inserts = [];
		foreach ( $res as $row ) {
			$inserts[] = [
				'log_timestamp' => $row->log_timestamp,
				'log_actor' => $row->log_actor,
				'log_namespace' => $row->log_namespace,
				'log_title' => $row->log_title,
				'log_page' => $row->log_page,
				'log_comment_id' => $row->log_comment_id,
				'log_type' => 'stabilization',
				'log_action' => 'add'
			];
		}
		if ( $db->insert( 'logging', $inserts, __METHOD__ ) ) {
			return Status::newGood( [ 'migrate_log' => count( $inserts ) ] );
		}
		return Status::newFatal( 'Failed to migrate log' );
	}
}
