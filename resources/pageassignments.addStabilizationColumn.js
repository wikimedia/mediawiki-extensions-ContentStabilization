( ( mw ) => {

	mw.hook( 'BSPageAssignmentsOverviewPanelInit' ).add( ( gridCfg ) => {
		gridCfg.columns.last_stable_date_display = { // eslint-disable-line camelcase
			headerText: mw.message( 'contentstabilization-column-last-stable' ).plain(),
			sortable: true,
			filter: { type: 'date' },
			valueParser: ( val ) => {
				if ( !val ) {
					return mw.message( 'contentstabilization-no-stable' ).plain();
				}

				return val;
			}
		};
	} );

} )( mediaWiki );
