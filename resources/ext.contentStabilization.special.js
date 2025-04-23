$( () => {
	const $cnt = $( '#contentstabilization' );
	if ( $cnt.length === 0 ) {
		return;
	}
	const panel = new OOJSPlus.ui.data.GridWidget( {
		deletable: false,
		style: 'differentiate-rows',
		exportable: true,
		columns: {
			page_display_text: {
				headerText: mw.message( 'contentstabilization-overview-header-title' ).text(),
				type: 'url',
				sortable: true,
				filter: {
					type: 'text'
				},
				urlProperty: 'page_link',
				autoClosePopup: true
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
						{ data: 'first-unstable', label: mw.message( 'contentstabilization-status-first-unstable' ).text() }
					]
				},
				autoClosePopup: true
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
				filter: { type: 'user' }
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
			sorter: {
				page_title: {
					direction: 'ASC'
				}
			}
		} ),
		provideExportData: function () {
			const dfd = $.Deferred(),
				store = new OOJSPlus.ui.data.store.RemoteRestStore( {
					path: 'content_stabilization/list',
					sorter: {
						page_title: {
							direction: 'ASC'
						}
					}
				} );
			store.loadAll().done( ( response ) => {
				const $table = $( '<table>' );
				let $row = $( '<tr>' ),
					$cell = $( '<td>' );
				$cell.append(
					mw.message( 'contentstabilization-overview-header-page-id' ).text()
				);
				$row.append( $cell );

				$cell = $( '<td>' );
				$cell.append(
					mw.message( 'contentstabilization-overview-header-title' ).plain()
				);
				$row.append( $cell );

				$cell = $( '<td>' );
				$cell.append(
					mw.message( 'contentstabilization-overview-header-page-namespace' ).plain()
				);
				$row.append( $cell );

				$cell = $( '<td>' );
				$cell.append(
					mw.message( 'contentstabilization-overview-header-status' ).plain()
				);
				$row.append( $cell );

				$cell = $( '<td>' );
				$cell.append(
					mw.message( 'contentstabilization-overview-header-is-in-sync' ).plain()
				);
				$row.append( $cell );

				$table.append( $row );

				const namespaces = mw.config.get( 'wgFormattedNamespaces' );
				for ( const id in response ) {
					if ( !response.hasOwnProperty( id ) ) {
						continue;
					}
					const record = response[ id ];
					$row = $( '<tr>' );

					$cell = $( '<td>' );
					$cell.append( record.page_id );
					$row.append( $cell );

					$cell = $( '<td>' );
					$cell.append( record.page_title );
					$row.append( $cell );

					$cell = $( '<td>' );
					if ( record.page_namespace === 0 ) {
						$cell.append( mw.message( 'blanknamespace' ).text() );
					} else {
						$cell.append( namespaces[ record.page_namespace ] );
					}

					$row.append( $cell );

					$cell = $( '<td>' );
					$cell.append( record.status );
					$row.append( $cell );

					$cell = $( '<td>' );
					// Is in sync?
					$cell.append( record.has_changed_inclusions ? 'false' : 'true' );
					$row.append( $cell );

					$table.append( $row );
				}

				dfd.resolve( '<table>' + $table.html() + '</table>' );
			} ).fail( () => {
				dfd.reject( 'Failed to load data' );
			} );

			return dfd.promise();
		}
	} );

	$cnt.append( panel.$element );
} );
