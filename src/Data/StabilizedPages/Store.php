<?php

namespace MediaWiki\Extension\ContentStabilization\Data\StabilizedPages;

use BadMethodCallException;
use JakubOnderka\PhpParallelLint\IWriter;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Language\Language;
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

	/** @var Language */
	private $language;

	/**
	 * @param StabilizationLookup $lookup
	 * @param ILoadBalancer $lb
	 * @param array $enabledNamespace
	 * @param Language $language
	 */
	public function __construct(
		StabilizationLookup $lookup, ILoadBalancer $lb, array $enabledNamespace, Language $language
	) {
		$this->lookup = $lookup;
		$this->lb = $lb;
		$this->enabledNamespace = $enabledNamespace;
		$this->language = $language;
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
		return new Reader( $this->lb, $this->lookup, $this->enabledNamespace, $this->language );
	}

	/**
	 * @return IWriter
	 */
	public function getWriter() {
		throw new BadMethodCallException();
	}
}
