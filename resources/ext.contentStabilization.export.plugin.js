mw.hook( 'pdfcreator.export.data' ).add( ( dialog, data ) => {
	if ( !mw.config.get( 'wgStabilizedRevisionId' ) ) {
		return;
	}
	if ( mw.util.getParamValue( 'stable' ) ) {
		const stableParam = mw.util.getParamValue( 'stable' );
		data.stable = stableParam;
	}
	data.revId = mw.config.get( 'wgStabilizedRevisionId' );
} );
