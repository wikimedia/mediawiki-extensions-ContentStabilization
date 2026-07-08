<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Galaxy;

use BlueSpice\GalaxyDistributionConnector\NamespaceSettings\INamespaceSetting;
use MediaWiki\Message\Message;

class AddNamespaceSettings implements INamespaceSetting {

	/**
	 * @return Message
	 */
	public function getLabel(): Message {
		return Message::newFromKey( 'contentstabilization-label-stabilize-ns' );
	}

	/**
	 * @return Message
	 */
	public function getDescription(): Message {
		return Message::newFromKey( 'contentstabilization-label-stabilize-ns-desc' );
	}

	/**
	 * @param int $namespace
	 * @param mixed $value
	 * @return void
	 */
	public function apply( int $namespace, mixed $value ): void {
		$GLOBALS['wgContentStabilizationEnabledNamespaces'] = $GLOBALS['wgContentStabilizationEnabledNamespaces'] ?? [];
		if ( !$value && in_array( $namespace, $GLOBALS['wgContentStabilizationEnabledNamespaces'] ) ) {
			$GLOBALS['wgContentStabilizationEnabledNamespaces'] = array_diff(
				$GLOBALS['wgContentStabilizationEnabledNamespaces'],
				[ $namespace ]
			);
		} elseif ( $value && !in_array( $namespace, $GLOBALS['wgContentStabilizationEnabledNamespaces'] ) ) {
			$GLOBALS['wgContentStabilizationEnabledNamespaces'][] = $namespace;
		}
	}
}
