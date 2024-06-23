( function ( mw ) {

	mw.hook( 'enhanced.versionhistory' ).add( ( gridCfg ) => {
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
	} );

}( mediaWiki ) );
