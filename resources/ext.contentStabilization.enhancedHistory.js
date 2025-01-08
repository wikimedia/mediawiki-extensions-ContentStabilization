( function ( mw ) {

	mw.hook( 'enhanced.versionhistory' ).add( function ( gridCfg ) {
		gridCfg.style = '';

		gridCfg.columns.sp_state = {
			headerText: mw.message( 'contentstabilization-versionhistory-grid-header-state' ).text(),
			type: 'text',
			sortable: false,
			hidden: !mw.user.options.get( 'history-show-sp_state' )
		};

		gridCfg.columns.sp_approver = {
			headerText: mw.message( 'contentstabilization-versionhistory-grid-header-approver' ).text(),
			type: 'user',
			showImage: true,
			sortable: false,
			hidden: !mw.user.options.get( 'history-show-sp_approver' )
		};

		gridCfg.columns.sp_approve_ts = {
			headerText: mw.message( 'contentstabilization-versionhistory-grid-header-approval-date' ).text(),
			type: 'text',
			sortable: false,
			hidden: !mw.user.options.get( 'history-show-sp_approve_ts' )
		};

		gridCfg.columns.sp_approve_comment = {
			headerText: mw.message( 'contentstabilization-versionhistory-grid-header-approval-comment' ).text(),
			type: 'text',
			sortable: false,
			hidden: !mw.user.options.get( 'history-show-sp_approve_comment' )
		};
		gridCfg.columns.removeApproval = {
			type: 'remove-approval',
			title: mw.message( 'contentstabilization-versionhistory-grid-header-remove-approval-title' ).text(),
			actionId: 'removeApproval',
			icon: 'unFlag'
		};
	} );

	// eslint-disable-next-line prefer-arrow-callback
	mw.hook( 'enhanced.versionhistory.rowAction' ).add( function ( action, row ) {
		if ( action === 'removeApproval' && row.sp_approved ) {
			const revId = parseInt( row.id );
			OO.ui.confirm( mw.message( 'contentstabilization-versionhistory-remove-approval-confirm' ).text() ).done(
				// eslint-disable-next-line prefer-arrow-callback
				function ( confirmed ) {
					if ( !confirmed ) {
						return;
					}
					$.ajax( {
						url: mw.util.wikiScript( 'rest' ) + '/content_stabilization/' + revId,
						type: 'DELETE',
						success: function () {
							location.reload();
						}
					} );
				} );
		}
	} );

}( mediaWiki ) );
