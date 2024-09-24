( ( mw ) => {

	mw.hook( 'BSPageAssignmentsOverviewPanelInit' ).add( ( gridCfg ) => {
		gridCfg.columns.last_stable_date = {
			headerText: mw.message( 'contentstabilization-column-last-stable' ).plain(),
			sortable: true,
			filter: { type: 'date' },
			valueParser: ( val ) => {
				if ( !val ) {
					return mw.message( 'contentstabilization-no-stable' ).plain();
				}

				const date = Ext.Date.parse( val, 'YmdHis' ),
					dateRenderer = Ext.util.Format.dateRenderer( 'Y-m-d, H:i' );

				return dateRenderer( date );
			}
		};
	} );

} )( mediaWiki );
