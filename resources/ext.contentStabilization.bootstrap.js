window.ext = window.ext || {};
window.ext.contentStabilization = {
	list: function ( page ) {
		if ( !page ) {
			return $.Deferred().reject( 'ext.contentStabilization.list: page must be a string' ).promise();
		}
		return ext.contentStabilization._api.get( '', { page: page } );
	},
	setStablePoint: function ( page, comment ) {
		if ( !page ) {
			return $.Deferred().reject( 'ext.contentStabilization.setStablePoint: page must be a string' ).promise();
		}
		return ext.contentStabilization._api.post( '', JSON.stringify( { page: page, comment: comment } ) );
	},
	deleteStablePoint: function ( revid ) {
		if ( !revid ) {
			return $.Deferred().reject( 'ext.contentStabilization.deleteStablePoint: revid is required' ).promise();
		}
		return ext.contentStabilization._api.delete( revid );
	},
	_api: {
		get: function ( path, params ) {
			return ext.contentStabilization._api._ajax( path, params );
		},
		post: function ( path, params ) {
			return ext.contentStabilization._api._ajax( path, params, 'POST' );
		},
		delete: function ( path, params ) {
			return ext.contentStabilization._api._ajax( path, params, 'DELETE' );
		},
		_requests: {},
		_ajax: function ( path, data, method ) {
			data = data || {};
			let dfd = $.Deferred(),
				finalPath = mw.util.wikiScript( 'rest' ) + '/content_stabilization';

			if ( path ) {
				finalPath = '/' + path;
			}
			ext.contentStabilization._api._requests[ path ] = $.ajax( {
				method: method,
				url: finalPath,
				data: data,
				contentType: 'application/json',
				dataType: method === 'DELETE' ? '' : 'json',
				beforeSend: function () {
					if ( ext.contentStabilization._api._requests.hasOwnProperty( path ) ) {
						ext.contentStabilization._api._requests[ path ].abort();
					}
				}
			} ).done( function ( response ) {
				delete ( ext.contentStabilization._api._requests[ path ] );
				dfd.resolve( response );
			} ).fail( function ( jgXHR, type, status ) {
				delete ( this._requests[ path ] );
				if ( type === 'error' ) {
					dfd.reject( {
						error: jgXHR.responseJSON || jgXHR.responseText
					} );
				}
				dfd.reject( { type: type, status: status } );
			}.bind( this ) );

			return dfd.promise();
		}
	},
	ui: {
		testing: {}
	}
};

// Handle approval link
$( function () {
	$( document ).on( 'click', '#contentstabilization-stabilize-link,#ca-cs-approve', function ( e ) {
		mw.loader.using( 'ext.contentStabilization.approve' ).then( function () {
			e.preventDefault();
			const dialog = new ext.contentStabilization.ui.ApproveDialog( {
					page: mw.config.get( 'wgPageName' )
				} ),
				manager = new OO.ui.WindowManager();
			$( 'body' ).append( manager.$element );
			manager.addWindows( [ dialog ] );
			manager.openWindow( dialog ).closed.then( function ( data ) {
				if ( data && data.action === 'approve' ) {
					window.location.reload();
				}
			} );
		} );
	} );
} );
