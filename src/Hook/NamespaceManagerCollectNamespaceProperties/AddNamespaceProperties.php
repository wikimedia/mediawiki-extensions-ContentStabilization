<?php

namespace MediaWiki\Extension\ContentStabilization\Hook\NamespaceManagerCollectNamespaceProperties;

class AddNamespaceProperties {

	/**
	 * @inheritDoc
	 */
	public function onNamespaceManagerCollectNamespaceProperties(
		int $namespaceId,
		array $globals,
		array &$properties
	): void {
		$properties['contentstabilization'] = in_array(
			$namespaceId,
			$globals['wgContentStabilizationEnabledNamespaces'] ?? []
		);
	}

}
