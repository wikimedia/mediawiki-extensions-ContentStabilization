<?php

namespace MediaWiki\Extension\ContentStabilization\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ContentStabilization\ContentStabilizer;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use TitleFactory;

abstract class StabilizerHandler extends SimpleHandler {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var Title|null */
	private $title = null;

	/** @var ContentStabilizer */
	private $stabilizer;

	/** @var StabilizationLookup */
	private $lookup;

	/**
	 * @param TitleFactory $titleFactory
	 * @param ContentStabilizer $stabilizer
	 * @param StabilizationLookup $lookup
	 */
	public function __construct(
		TitleFactory $titleFactory, ContentStabilizer $stabilizer, StabilizationLookup $lookup
	) {
		$this->titleFactory = $titleFactory;
		$this->stabilizer = $stabilizer;
		$this->lookup = $lookup;
	}

	/**
	 * @return PageIdentity
	 * @throws HttpException
	 */
	protected function getPage(): PageIdentity {
		$this->assertTitle();
		return $this->title->toPageIdentity();
	}

	/**
	 * @return string
	 */
	protected function getPageParamValue(): string {
		return '';
	}

	/**
	 * @return void
	 * @throws HttpException
	 */
	private function setTitle() {
		$page = $this->getPageParamValue();

		$title = $this->titleFactory->newFromText( $page );
		if ( !$title ) {
			throw new HttpException( 'Invalid page title', 400 );
		}
		if ( !$title->exists() ) {
			throw new HttpException( 'Page does not exist', 404 );
		}
		if ( !$this->stabilizer->isEligibleForStabilization( $title->toPageIdentity() ) ) {
			throw new HttpException( 'Stabilization not enabled for this page', 400 );
		}
		$this->title = $title;
	}

	/**
	 * @return void
	 * @throws HttpException
	 */
	private function assertTitle() {
		if ( $this->title === null ) {
			$this->setTitle();
		}
	}

	/**
	 * @return ContentStabilizer
	 */
	protected function getStabilizer(): ContentStabilizer {
		return $this->stabilizer;
	}

	/**
	 * @return StabilizationLookup
	 */
	protected function getLookup(): StabilizationLookup {
		return $this->lookup;
	}

	/**
	 * @return User
	 */
	protected function getUser(): User {
		return RequestContext::getMain()->getUser();
	}
}
