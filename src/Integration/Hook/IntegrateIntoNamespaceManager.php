<?php

//phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use Config;
use Message;
use NamespaceInfo;

class IntegrateIntoNamespaceManager {

	/**
	 *
	 * @var Config
	 */
	protected $config = null;

	/**
	 *
	 * @var NamespaceInfo
	 */
	protected $namespaceInfo = null;

	/**
	 * @param Config $config
	 * @param NamespaceInfo $namespaceInfo
	 */
	public function __construct( Config $config, NamespaceInfo $namespaceInfo ) {
		$this->config = $config;
		$this->namespaceInfo = $namespaceInfo;
	}

	/**
	 * @param array &$aMetaFields
	 *
	 * @return bool
	 */
	public function onNamespaceManager__getMetaFields( &$aMetaFields ) {
		$aMetaFields[] = [
			'name' => 'contentstabilization',
			'type' => 'boolean',
			'label' => Message::newFromKey( 'contentstabilization-label-stabilize-ns' )->plain(),
			'filter' => [
				'type' => 'boolean'
			],
		];
		return true;
	}

	/**
	 * @param array &$aResults
	 *
	 * @return bool
	 */
	public function onBSApiNamespaceStoreMakeData( &$aResults ) {
		$current = $this->config->get( 'ContentStabilizationEnabledNamespaces' );
		$iResults = count( $aResults );
		for ( $i = 0; $i < $iResults; $i++ ) {
			$aResults[ $i ][ 'contentstabilization' ] = [
				'value' => in_array( $aResults[ $i ][ 'id' ], $current ),
				'disabled' => $aResults[ $i ]['isTalkNS']
			];
		}
		return true;
	}

	/**
	 * @param array &$namespaceDefinitions
	 * @param int &$ns
	 * @param array $additionalSettings
	 * @param bool $useInternalDefaults
	 *
	 * @return bool
	 */
	public function onNamespaceManager__editNamespace(
		&$namespaceDefinitions, &$ns, $additionalSettings, $useInternalDefaults = false
	) {
		if ( $this->namespaceInfo->isTalk( $ns ) ) {
			// Stabilization can not be activated for TALK namespaces!
			return true;
		}

		if ( !$useInternalDefaults && isset( $additionalSettings['contentstabilization'] ) ) {
			$namespaceDefinitions[$ns][ 'contentstabilization' ] = $additionalSettings['contentstabilization'];
		} else {
			$namespaceDefinitions[$ns][ 'contentstabilization' ] = false;
		}
		return true;
	}

	/**
	 * @param string &$saveContent
	 * @param string $constName
	 * @param int $nsId
	 * @param array $definition
	 *
	 * @return bool
	 */
	public function onNamespaceManager__writeNamespaceConfiguration(
		&$saveContent, $constName, $nsId, $definition
	) {
		$current = $this->config->get( 'ContentStabilizationEnabledNamespaces' );
		if ( $nsId === null || $this->namespaceInfo->isTalk( $nsId ) ) {
			// Stabilization can not be activated for TALK namespaces!
			return true;
		}

		$currentlyActivated = in_array( $nsId, $current );

		$explicitlyDeactivated = false;
		if ( isset( $definition['contentstabilization'] ) && $definition['contentstabilization'] === false ) {
			$explicitlyDeactivated = true;
		}

		$explicitlyActivated = false;
		if ( isset( $definition['contentstabilization'] ) && $definition['contentstabilization'] === true ) {
			$explicitlyActivated = true;
		}

		if ( ( $currentlyActivated && !$explicitlyDeactivated ) || $explicitlyActivated ) {
			$saveContent .= "\$GLOBALS['wgContentStabilizationEnabledNamespaces'][] = {$constName};\n";
		}

		return true;
	}
}
