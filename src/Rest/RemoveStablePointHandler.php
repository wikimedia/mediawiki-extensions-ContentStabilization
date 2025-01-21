<?php

namespace MediaWiki\Extension\ContentStabilization\Rest;

use MediaWiki\Extension\ContentStabilization\ContentStabilizer;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class RemoveStablePointHandler extends StabilizerHandler {

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

	/**
	 * @return bool
	 */
	public function needsWriteAccess() {
		return true;
	}

	/**
	 * @return Response
	 * @throws HttpException
	 */
	public function execute() {
		$revision = $this->revisionStore->getRevisionById( $this->getValidatedParams()['rev_id'] );
		if ( !( $revision instanceof RevisionRecord ) ) {
			throw new HttpException( 'Revision not found', 404 );
		}
		$point = $this->getLookup()->getStablePointForRevision( $revision );
		if ( !( $point instanceof StablePoint ) ) {
			throw new HttpException( 'Revision is not a stable point', 500 );
		}
		try {
			$this->getStabilizer()->removeStablePoint( $point, $this->getUser() );
			return $this->getResponseFactory()->create();
		} catch ( \Exception $e ) {
			throw new HttpException( $e->getMessage(), 500 );
		}
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return parent::getParamSettings() + [
			'rev_id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
