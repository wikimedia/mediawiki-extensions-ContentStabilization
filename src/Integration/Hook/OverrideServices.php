<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\ContentStabilization\Override\PageRestHelperFactory;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\MediaWikiServices;

class OverrideServices implements MediaWikiServicesHook {

	/**
	 * @inheritDoc
	 */
	public function onMediaWikiServices( $services ) {
		$services->redefineService(
			'PageRestHelperFactory',
			static function ( MediaWikiServices $services ) {
				return new PageRestHelperFactory(
					new ServiceOptions(
						PageRestHelperFactory::CONSTRUCTOR_OPTIONS,
						$services->getMainConfig()
					),
					$services->getRevisionLookup(),
					$services->getRevisionRenderer(),
					$services->getTitleFormatter(),
					$services->getPageStore(),
					$services->getParsoidOutputStash(),
					$services->getStatsdDataFactory(),
					$services->getParserOutputAccess(),
					$services->getParsoidSiteConfig(),
					$services->getHtmlTransformFactory(),
					$services->getContentHandlerFactory(),
					$services->getLanguageFactory(),
					$services->getRedirectStore(),
					$services->getLanguageConverterFactory(),
					$services->getTitleFactory(),
					$services->getConnectionProvider(),
					$services->getChangeTagsStore(),
					$services->getStatsFactory(),
					$services->getService( 'ContentStabilization.Lookup' )
				);
			}
		);
	}
}
