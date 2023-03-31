<?php

namespace MediaWiki\Extension\ContentStabilization\Rest;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use Wikimedia\ParamValidator\ParamValidator;

class ListStablePointsHandler extends StabilizerHandler {

	/**
	 * @return Response
	 * @throws HttpException
	 */
	public function execute() {
		$stablePoints = $this->getLookup()->getStablePointsForPage( $this->getPage() );
		return $this->getResponseFactory()->createJson( [
			'stable_points' => $stablePoints
		] );
	}

	/**
	 * @return string
	 */
	protected function getPageParamValue(): string {
		return $this->getValidatedParams()['page'];
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'page' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
