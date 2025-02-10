<?php

use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\Extension\ContentStabilization\ContentStabilizer;
use MediaWiki\Extension\ContentStabilization\InclusionManager;
use MediaWiki\Extension\ContentStabilization\InclusionMode;
use MediaWiki\Extension\ContentStabilization\StabilizationLog;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\Storage\StablePointStore;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	// Internal use only
	'ContentStabilization._Config' => static function ( MediaWikiServices $services ) {
		return new GlobalVarConfig( 'wgContentStabilization' );
	},
	// Internal use only
	'ContentStabilization._Store' => static function ( MediaWikiServices $services ) {
		return new StablePointStore(
			$services->getDBLoadBalancer(),
			$services->getUserFactory(),
			$services->getRevisionStore(),
			$services->getRepoGroup()
		);
	},
	// Internal use only
	'ContentStabilization._InclusionManager' => static function ( MediaWikiServices $services ) {
		$inclusionModesAttribute = ExtensionRegistry::getInstance()->getAttribute(
			'ContentStabilizationInclusionModes'
		);
		$inclusionModes = [];
		// Because dynamic configs (from ConfigManager) are not loaded yet, we cannot inject enable mode yet -.-
		foreach ( $inclusionModesAttribute as $key => $spec ) {
			$instance = $services->getObjectFactory()->createObject( $spec );
			if ( !( $instance instanceof InclusionMode ) ) {
				throw new InvalidArgumentException(
					"ContentStabilizationInclusionModes[$key] must be an instance of InclusionMode"
				);
			}
			$inclusionModes[$key] = $instance;
		}
		return new InclusionManager(
			$services->getDBLoadBalancer(),
			$services->getWikiPageFactory(),
			$services->getRevisionLookup(),
			$services->getRepoGroup(),
			$services->getService( 'ContentStabilization._Config' ),
			$services->getParserFactory(),
			$inclusionModes
		);
	},
	// Internal use only
	'ContentStabilization._DebugLogger' => static function ( MediaWikiServices $services ) {
		return LoggerFactory::getInstance( 'stabilization' );
	},
	'ContentStabilization._SpecialLogLogger' => static function ( MediaWikiServices $services ) {
		return new StabilizationLog();
	},
	// This service is meant to be used to manage stabilization data
	'ContentStabilization.Stabilizer' => static function ( MediaWikiServices $services ) {
		$store = $services->getService( 'ContentStabilization._Store' );
		$inclusionManager = $services->getService( 'ContentStabilization._InclusionManager' );
		$lookup = $services->getService( 'ContentStabilization.Lookup' );
		return new ContentStabilizer(
			$store, $lookup, $inclusionManager, $services->getPermissionManager(), $services->getHookContainer()
		);
	},
	// This service is meant to be used to access stabilization data
	'ContentStabilization.Lookup' => static function ( MediaWikiServices $services ) {
		$config = $services->getService( 'ContentStabilization._Config' );
		return new StabilizationLookup(
			$services->getService( 'ContentStabilization._Store' ),
			$services->getService( 'ContentStabilization._InclusionManager' ),
			$services->getRevisionStore(),
			$services->getUserGroupManager(),
			$config,
			$services->getHookContainer()
		);
	}
];
