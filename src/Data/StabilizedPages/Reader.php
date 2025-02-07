<?php

namespace MediaWiki\Extension\ContentStabilization\Data\StabilizedPages;

use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Language\Language;
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
	 * @var Language
	 */
	private $language;

	/**
	 * @param ILoadBalancer $lb
	 * @param StabilizationLookup $lookup
	 * @param array $enabledNamespaces
	 * @param Language $language
	 */
	public function __construct(
		ILoadBalancer $lb, StabilizationLookup $lookup, array $enabledNamespaces, Language $language
	) {
		parent::__construct();
		$this->lb = $lb;
		$this->lookup = $lookup;
		$this->enabledNamespaces = $enabledNamespaces;
		$this->language = $language;
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
		return new PrimaryDataProvider( $db, $this->getSchema(), $this->enabledNamespaces, $this->language );
	}

	/**
	 * @inheritDoc
	 */
	public function makeSecondaryDataProvider() {
		return new SecondaryDataProvider( $this->lookup, $this->language );
	}
}
