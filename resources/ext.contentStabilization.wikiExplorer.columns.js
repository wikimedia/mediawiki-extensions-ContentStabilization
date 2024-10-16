mw.hook( 'bs.wikiexplorer.oojs.columns' ).add( function ( columns ) {
	columns.contentstabilization_state = {
		headerText: mw.message( 'contentstabilization-wikiexplorer-state' ).text(),
		type: 'boolean',
		valueParser: function ( val, row ) {
			if (
				!row.hasOwnProperty( 'is_contentstabilization_enabled' ) ||
				row.is_contentstabilization_enabled === false
			) {
				return null;
			}

			return val;
		},
		filter: {
			type: 'boolean',
			trueLabel: mw.msg( 'contentstabilization-wikiexplorer-filter-approved' ),
			falseLabel: mw.msg( 'contentstabilization-wikiexplorer-filter-not-approved' )
		},
		sortable: true
	};

	columns.contentstabilization_date = {
		headerText: mw.message( 'contentstabilization-wikiexplorer-date' ).text(),
		type: 'date',
		valueParser: function ( val, row ) {
			if (
				!row.hasOwnProperty( 'is_contentstabilization_enabled' ) ||
				row.is_contentstabilization_enabled === false
			) {
				return null;
			}

			if ( val === null ) {
				return '';
			}

			// MW to ISO
			// YYYYMMDDHHMMSS => YYYY-MM-DDTHH
			const match = val.match(
				/^(\d{4})(\d{2})(\d{2}).*$/
			);
			const date = new Date( match[ 1 ] + '-' + match[ 2 ] + '-' + match[ 3 ] );
			if ( !date ) {
				return '';
			}

			return date.toLocaleDateString();
		},
		filter: {
			type: 'date'
		},
		hidden: true,
		sortable: true
	};

	columns.contentstabilization_is_new_available = {
		headerText: mw.message( 'contentstabilization-wikiexplorer-is-new-available' ).text(),
		type: 'boolean',
		valueParser: function ( val, row ) {
			if (
				!row.hasOwnProperty( 'is_contentstabilization_enabled' ) ||
				row.is_contentstabilization_enabled === false
			) {
				return null;
			}

			return val;
		},
		filter: {
			type: 'boolean',
			trueLabel: mw.msg( 'contentstabilization-wikiexplorer-filter-has-draft' ),
			falseLabel: mw.msg( 'contentstabilization-wikiexplorer-filter-no-draft' )
		},
		sortable: true
	};
} );
