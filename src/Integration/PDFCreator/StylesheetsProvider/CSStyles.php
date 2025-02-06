<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\PDFCreator\StylesheetsProvider;

use MediaWiki\Extension\PDFCreator\IStylesheetsProvider;
use MediaWiki\Extension\PDFCreator\Utility\ExportContext;

class CSStyles implements IStylesheetsProvider {
	/**
	 * @param string $module
	 * @param ExportContext $context
	 * @return array
	 */
	public function execute( string $module, ExportContext $context ): array {
		$dir = dirname( __DIR__, 4 );
		return [
			'stabilized-export.css' => "$dir/resources/stabilized-export.css"
		];
	}
}
