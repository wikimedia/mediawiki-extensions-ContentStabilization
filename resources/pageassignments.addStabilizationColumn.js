$( document ).on( 'BSPageAssignmentsOverviewPanelInit', function ( e, sender, cols, fields, actions ) {
	fields.push( 'last_stable_date' );

	cols.push( {
		text: mw.message( 'contentstabilization-column-last-stable' ).plain(),
		xtype: 'datecolumn',
		format: 'Y-m-d H:i', // Doesn't work with custom renderer :(
		dataIndex: 'last_stable_date',
		sortable: true,
		filter: {
			type: 'date'
		},
		renderer: function ( value, metaData, record, rowIndex, colIndex, store, view ) {
			if ( !value ) {
				return '<em>' + mw.message( 'contentstabilization-no-stable' ).plain() + '</em>';
			}

			const date = Ext.Date.parse( value, 'YmdHis' ),
				renderer = Ext.util.Format.dateRenderer( 'Y-m-d, H:i' );

			return renderer( date );
		}
	} );
} );
