<?php

//phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use BlueSpice\NamespaceManager\Hook\NamespaceManagerBeforePersistSettingsHook;
use MediaWiki\Config\Config;
use MediaWiki\Message\Message;
use MediaWiki\Title\NamespaceInfo;

class IntegrateIntoNamespaceManager implements NamespaceManagerBeforePersistSettingsHook {

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
		$unavailable = $this->config->get( 'ContentStabilizationUnavailableNamespaces' );
		$iResults = count( $aResults );
		for ( $i = 0; $i < $iResults; $i++ ) {
			$aResults[ $i ][ 'contentstabilization' ] = [
				'value' => in_array( $aResults[ $i ][ 'id' ], $current ),
				'disabled' => $aResults[ $i ]['isTalkNS'] || in_array( $aResults[$i]['id'], $unavailable )
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
	 * @inheritDoc
	 */
	public function onNamespaceManagerBeforePersistSettings(
		array &$configuration, int $id, array $definition, array $mwGlobals
	): void {
		$enabledNamespaces = $mwGlobals['wgContentStabilizationEnabledNamespaces'] ?? [];
		if ( $this->namespaceInfo->isTalk( $id ) ) {
			// Stabilization can not be activated for TALK namespaces!
			return;
		}
		$currentlyActivated = in_array( $id, $enabledNamespaces );

		$explicitlyDeactivated = false;
		if ( isset( $definition['contentstabilization'] ) && $definition['contentstabilization'] === false ) {
			$explicitlyDeactivated = true;
		}

		$explicitlyActivated = false;
		if ( isset( $definition['contentstabilization'] ) && $definition['contentstabilization'] === true ) {
			$explicitlyActivated = true;
		}

		if ( ( $currentlyActivated && !$explicitlyDeactivated ) || $explicitlyActivated ) {
			$configuration['wgContentStabilizationEnabledNamespaces'][] = $id;
		}
	}
}
