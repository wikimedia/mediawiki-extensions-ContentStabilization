<?php

namespace MediaWiki\Extension\ContentStabilization\Data\StabilizedPages;

use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use Wikimedia\Rdbms\ILoadBalancer;

class Reader extends \MWStake\MediaWiki\Component\DataStore\Reader {

	/**
	 * @var ILoadBalancer
	 */
	private $lb;

	/**
	 * @var StabilizationLookup
	 */
	private $lookup;

	/**
	 * @var array
	 */
	private $enabledNamespaces;

	/**
	 * @param ILoadBalancer $lb
	 * @param StabilizationLookup $lookup
	 * @param array $enabledNamespaces
	 */
	public function __construct( ILoadBalancer $lb, StabilizationLookup $lookup, array $enabledNamespaces ) {
		parent::__construct();
		$this->lb = $lb;
		$this->lookup = $lookup;
		$this->enabledNamespaces = $enabledNamespaces;
	}

	/**
	 * @return Schema
	 */
	public function getSchema() {
		return new Schema();
	}

	/**
	 * @param ReaderParams $params
	 *
	 * @return PrimaryDataProvider
	 */
	public function makePrimaryDataProvider( $params ) {
		$db = $this->lb->getConnection( DB_REPLICA );
		return new PrimaryDataProvider( $db, $this->getSchema(), $this->enabledNamespaces );
	}

	/**
	 * @inheritDoc
	 */
	public function makeSecondaryDataProvider() {
		return new SecondaryDataProvider( $this->lookup );
	}
}
