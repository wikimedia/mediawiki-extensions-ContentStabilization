<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\PDFCreator\StyleBlockProvider;

use MediaWiki\Extension\PDFCreator\Interface\IStyleBlocksProvider;
use MediaWiki\Extension\PDFCreator\Utility\ExportContext;

class CSStyleBlocks implements IStyleBlocksProvider {

	/**
	 * @param string $module
	 * @param ExportContext $context
	 * @return array
	 */
	public function execute( string $module, ExportContext $context ): array {
		$base = dirname( __DIR__, 4 ) . '/resources';
		$styles = file_get_contents( "$base/stabilized-export.css" );
		if ( !$styles ) {
			$styles = ' /** file not found */';
		}
		return [
			'ContentStabilization'  => $styles
		];
	}
}
