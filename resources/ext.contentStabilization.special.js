$( function () {
	var $cnt = $( '#contentstabilization' );
	if ( $cnt.length === 0 ) {
		return;
	}
	var panel = new OOJSPlus.ui.data.GridWidget( {
		deletable: false,
		style: 'differentiate-rows',
		columns: {
			page_title: {
				headerText: mw.message( 'contentstabilization-overview-header-title' ).text(),
				type: 'url',
				sortable: true,
				filter: {
					type: 'text'
				},
				urlProperty: 'page_link',
				valueParser: function ( value, row ) {
					return row.page_display_text;
				}
			},
			status: {
				headerText: mw.message( 'contentstabilization-overview-header-status' ).text(),
				type: 'text',
				sortable: true,
				filter: {
					type: 'list',
					list: [
						{ data: 'unstable', label: mw.message( 'contentstabilization-status-unstable' ).text() },
						{ data: 'stable', label: mw.message( 'contentstabilization-status-stable' ).text() },
						{ data: 'first-unstable', label: mw.message( 'contentstabilization-first-unstable' ).text() }
					]
				}
			},
			has_changed_inclusions: {
				headerText: mw.message( 'contentstabilization-overview-header-is-in-sync' ).text(),
				type: 'boolean',
				valueParser: function ( value, row ) {
					return value === false;
				}
			},
			last_approver: {
				headerText: mw.message( 'contentstabilization-overview-header-has-changed-last-approver' ).text(),
				type: 'user',
				sortable: true,
				filter: { type: 'text' }
			},
			last_stable_ts: {
				headerText: mw.message( 'contentstabilization-overview-header-has-changed-last-stable-ts' ).text(),
				type: 'text',
				sortable: true,
				width: 180
			},
			last_comment: {
				headerText: mw.message( 'contentstabilization-overview-header-has-changed-last-comment' ).text(),
				type: 'text'
			}
		},
		store: new OOJSPlus.ui.data.store.RemoteRestStore( {
			path: 'content_stabilization/list',
			pageSize: 25,
			sorter: {
				page_title: {
					direction: 'ASC'
				}
			}
		} )
	} );

	$cnt.append( panel.$element );
} );
