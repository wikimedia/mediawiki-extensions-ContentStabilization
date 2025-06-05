<?php

namespace MediaWiki\Extension\ContentStabilization;

use Exception;
use ManualLogEntry;
use MediaWiki\Permissions\Authority;

class StabilizationLog {
	/**
	 * @param StablePoint $stablePoint
	 *
	 * @return void
	 * @throws Exception
	 */
	public function stablePointAdded( StablePoint $stablePoint ) {
		$logEntry = new ManualLogEntry( 'stabilization', 'add' );
		$logEntry->setPerformer( $stablePoint->getApprover()->getUser() );
		$logEntry->setTarget( $stablePoint->getPage() );
		$logEntry->setComment( $stablePoint->getComment() );

		$logEntry->insert();
	}

	/**
	 * @param StablePoint $stablePoint
	 * @param Authority $remover
	 *
	 * @return void
	 * @throws Exception
	 */
	public function stablePointRemoved( StablePoint $stablePoint, Authority $remover ) {
		$logEntry = new ManualLogEntry( 'stabilization', 'remove' );
		$logEntry->setPerformer( $remover->getUser() );
		$logEntry->setTarget( $stablePoint->getPage() );
		$logEntry->setComment( '' );

		$logEntry->insert();
	}

	/**
	 * @param StablePoint $stablePoint
	 *
	 * @return void
	 * @throws Exception
	 */
	public function stablePointUpdated( StablePoint $stablePoint ) {
		$logEntry = new ManualLogEntry( 'stabilization', 'update' );
		$logEntry->setPerformer( $stablePoint->getApprover()->getUser() );
		$logEntry->setTarget( $stablePoint->getPage() );
		$logEntry->setComment( '' );

		$logEntry->insert();
	}
}
