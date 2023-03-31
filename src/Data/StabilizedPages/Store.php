<?php

namespace MediaWiki\Extension\ContentStabilization\Data\StabilizedPages;

use BadMethodCallException;
use JakubOnderka\PhpParallelLint\IWriter;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MWStake\MediaWiki\Component\DataStore\IReader;
use MWStake\MediaWiki\Component\DataStore\IStore;
use Wikimedia\Rdbms\ILoadBalancer;

class Store implements IStore {

	/** @var StabilizationLookup */
	private $lookup;

	/** @var ILoadBalancer */
	private ILoadBalancer $lb;

	/** @var array */
	private $enabledNamespace;

	/**
	 * @param StabilizationLookup $lookup
	 * @param ILoadBalancer $lb
	 * @param array $enabledNamespace
	 */
	public function __construct( StabilizationLookup $lookup, ILoadBalancer $lb, array $enabledNamespace ) {
		$this->lookup = $lookup;
		$this->lb = $lb;
		$this->enabledNamespace = $enabledNamespace;
	}

	/**
	 * @return Schema
	 */
	public function getSchema() {
		return new Schema();
	}

	/**
	 * @return IReader
	 */
	public function getReader() {
		return new Reader( $this->lb, $this->lookup, $this->enabledNamespace );
	}

	/**
	 * @return IWriter
	 */
	public function getWriter() {
		throw new BadMethodCallException();
	}
}
