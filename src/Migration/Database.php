<?php

namespace MediaWiki\Extension\ContentStabilization\Migration;

use Status;
use Wikimedia\Rdbms\ILoadBalancer;

class Database {

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var int */
	private $minQuality;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param int|null $minQuality
	 */
	public function __construct( ILoadBalancer $loadBalancer, ?int $minQuality = 1 ) {
		$this->loadBalancer = $loadBalancer;
		$this->minQuality = $minQuality;
	}

	/**
	 * @return Status
	 */
	public function migrate() {
		$value = [];
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		if ( $db->tableExists( 'flaggedrevs' ) ) {
			$s = $this->migrateFlaggedRevsTable();
			if ( !$s->isOK() ) {
				return $s;
			}
			$value = array_merge( $value, $s->getValue() );
		}
		if ( $db->tableExists( 'flaggedtemplates' ) ) {
			$s = $this->migrateFlaggedTemplatesTable();
			if ( !$s->isOK() ) {
				return $s;
			}
			$value = array_merge( $value, $s->getValue() );
		}

		if ( $db->tableExists( 'flaggedimages' ) ) {
			$s = $this->migrateFlaggedImagesTable();
			if ( !$s->isOK() ) {
				return $s;
			}
			$value = array_merge( $value, $s->getValue() );
		}

		return Status::newGood( $value );
	}

	/**
	 * @return Status
	 */
	private function migrateFlaggedRevsTable() {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		if ( !$db->fieldExists( 'flaggedrevs', 'fr_img_timestamp' ) ) {
			// Even though we do not perform the migration, we still return a good status
			// because if we get here, it means that there is a legacy FlaggedRevs table,
			// but it can not be migrated at all. It has a newer schema than what we can
			// handle.
			return Status::newGood( [ 'migrated_flaggedrevs' => -1 ] );
		}

		$migrated = 0;
		$batchSize = 100;
		$db->startAtomic( __METHOD__ );
		do {
			$res = $db->select(
				'flaggedrevs',
				[
					'fr_page_id', 'fr_rev_id', 'fr_timestamp', 'fr_user',
					'fr_quality', 'fr_img_timestamp', 'fr_img_sha1'
				],
				[ 'fr_quality >= ' . $db->addQuotes( $this->minQuality ) ],
				__METHOD__,
				[ 'LIMIT' => $batchSize ]
			);

			$numRows = $res->count();
			if ( $numRows ) {
				$inserts = [];
				$fileInserts = [];
				foreach ( $res as $row ) {
					$inserts[] = [
						'sp_page' => $row->fr_page_id,
						'sp_revision' => $row->fr_rev_id,
						'sp_time' => $row->fr_timestamp,
						'sp_user' => $row->fr_user
					];

					if ( $row->fr_img_timestamp && $row->fr_img_sha1 ) {
						$fileInserts[] = [
							'sfp_revision' => $row->fr_rev_id,
							'sfp_page' => $row->fr_page_id,
							'sfp_file_timestamp' => $row->fr_img_timestamp,
							'sfp_file_sha1' => $row->fr_img_sha1
						];

					}
				}
				$db->insert( 'stable_points', $inserts, __METHOD__, [ 'IGNORE' ] );
				if ( $fileInserts ) {
					$db->insert( 'stable_file_points', $fileInserts, __METHOD__, [ 'IGNORE' ] );
				}

			}
			$migrated += $numRows;
		} while ( $numRows >= $batchSize );
		$db->endAtomic( __METHOD__ );

		return Status::newGood( [ 'migrated_flaggedrevs' => $migrated ] );
	}

	/**
	 * @return Status
	 */
	private function migrateFlaggedTemplatesTable() {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		if ( !$db->fieldExists( 'flaggedtemplates', 'ft_namespace' ) ) {
			// Even though we do not perform the migration, we still return a good status
			// because if we get here, it means that there is a legacy FlaggedRevs table,
			// but it can not be migrated at all. It has a newer schema than what we can
			// handle.
			return Status::newGood( [ 'migrated_flaggedtemplates' => -1 ] );
		}

		$migrated = 0;
		$batchSize = 100;
		$db->startAtomic( __METHOD__ );
		do {
			$res = $db->select(
				[ 'flaggedtemplates', 'flaggedrevs' ],
				[ 'ft_rev_id', 'ft_namespace', 'ft_title', 'ft_tmp_rev_id', 'fr_page_id' ],
				[],
				__METHOD__,
				[ 'LIMIT' => $batchSize ],
				[ 'flaggedrevs' => [ 'INNER JOIN', 'ft_rev_id = fr_rev_id' ] ]
			);

			$numRows = $res->count();
			if ( $numRows ) {
				$inserts = [];
				foreach ( $res as $row ) {
					$inserts[] = [
						'st_revision' => $row->ft_rev_id,
						'st_page' => $row->fr_page_id,
						'st_transclusion_revision' => $row->ft_tmp_rev_id,
						'st_transclusion_namespace' => $row->ft_namespace,
						'st_transclusion_title' => $row->ft_title
					];
				}
				$db->insert( 'stable_transclusions', $inserts, __METHOD__, [ 'IGNORE' ] );
			}
			$migrated += $numRows;
		} while ( $numRows >= $batchSize );
		$db->endAtomic( __METHOD__ );

		return Status::newGood( [ 'migrated_flaggedtemplates' => $migrated ] );
	}

	/**
	 * @return Status
	 */
	private function migrateFlaggedImagesTable() {
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );

		$migrated = 0;
		$batchSize = 100;
		$db->startAtomic( __METHOD__ );
		do {
			$res = $db->select(
				[ 'flaggedimages', 'flaggedrevs' ],
				[ 'fi_rev_id', 'fi_name', 'fi_img_timestamp', 'fi_img_sha1', 'fr_page_id' ],
				[],
				__METHOD__,
				[ 'LIMIT' => $batchSize ],
				[ 'flaggedrevs' => [ 'INNER JOIN', 'fi_rev_id = fr_rev_id' ] ]
			);

			$numRows = $res->count();
			if ( $numRows ) {
				$inserts = [];
				foreach ( $res as $row ) {
					$inserts[] = [
						'sft_revision' => $row->fi_rev_id,
						'sft_page' => $row->fr_page_id,
						// No data :(
						'sft_file_revision' => -1,
						'sft_file_name' => $row->fi_name,
						'sft_file_timestamp' => $row->fi_img_timestamp,
						'sft_file_sha1' => $row->fi_img_sha1
					];
				}
				$db->insert( 'stable_file_transclusions', $inserts, __METHOD__, [ 'IGNORE' ] );
			}
			$migrated += $numRows;
		} while ( $numRows >= $batchSize );
		$db->endAtomic( __METHOD__ );

		return Status::newGood( [ 'migrated_flaggedimages' => $migrated ] );
	}
}
