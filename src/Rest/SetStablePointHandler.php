<?php

namespace MediaWiki\Extension\ContentStabilization\Rest;

use MediaWiki\Extension\ContentStabilization\ContentStabilizer;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\Validator\BodyValidator;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class SetStablePointHandler extends StabilizerHandler {

	/** @var RevisionStore */
	private $revisionStore;

	/**
	 * @param TitleFactory $titleFactory
	 * @param ContentStabilizer $stabilizer
	 * @param StabilizationLookup $lookup
	 * @param RevisionStore $revisionStore
	 */
	public function __construct(
		TitleFactory $titleFactory, ContentStabilizer $stabilizer,
		StabilizationLookup $lookup, RevisionStore $revisionStore
	) {
		parent::__construct( $titleFactory, $stabilizer, $lookup );
		$this->revisionStore = $revisionStore;
	}

	public function needsWriteAccess() {
		return true;
	}

	/**
	 * @return Response
	 * @throws HttpException
	 */
	public function execute() {
		$revision = $this->revisionStore->getRevisionByPageId( $this->getPage()->getId() );
		if ( !( $revision instanceof RevisionRecord ) ) {
			throw new HttpException( 'Revision not found', 404 );
		}
		$body = $this->getValidatedBody();
		$comment = $body['comment'] ?? '';
		$stablePoint = $this->getLookup()->getStablePointForRevision( $revision );
		if ( $stablePoint instanceof StablePoint ) {
			$point = $this->getStabilizer()->updateStablePoint( $stablePoint, $this->getUser(), $comment );
		} else {
			$point = $this->getStabilizer()->addStablePoint( $revision, $this->getUser(), $comment );
		}
		if ( !( $point instanceof StablePoint ) ) {
			throw new HttpException( 'Failed to store stable point', 500 );
		}
		return $this->getResponseFactory()->createJson( [
			'stable_point' => $point
		] );
	}

	/**
	 * @return string
	 */
	protected function getPageParamValue(): string {
		return $this->getValidatedBody()['page'];
	}

	/**
	 * @param string $contentType
	 * @return BodyValidator
	 */
	public function getBodyValidator( $contentType ) {
		if ( $contentType === 'application/json' ) {
			return new JsonBodyValidator( [
				'comment' => [
					ParamValidator::PARAM_REQUIRED => false,
				],
				'page' => [
					ParamValidator::PARAM_REQUIRED => true,
				],
			] );
		}
		return parent::getBodyValidator( $contentType );
	}
}
