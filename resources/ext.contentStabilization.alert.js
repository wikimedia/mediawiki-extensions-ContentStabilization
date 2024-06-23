( function ( mw, $ ) {

	$( () => {
		const $alert = $( '.alert.alert-warning' );

		if ( $alert.length < 1 ) {
			return;
		}
		const btn = OO.ui.infuse( '#content-stabilization-banner-info-btn' ),

			infoBtn = new OO.ui.PopupButtonWidget( {
				framed: false,
				icon: 'infoFilled',
				title: mw.message( 'contentstabilization-state-draft-info-btn-title' ).text(),
				popup: {
					$content: getPopupContent( btn.data ),
					padded: true,
					align: 'force-left'
				}
			} );
		$( '#content-stabilization-banner-info-btn' ).html( infoBtn.$element );

	} );

	function getPopupContent( data ) {
		const layout = new OO.ui.PanelLayout( {
			classes: [ 'contentstabilization-state-draft-info-popup' ],
			padded: true,
			expanded: false
		} );
		layout.$element.append(
			new OO.ui.LabelWidget( {
				label: mw.message( 'contentstabilization-state-draft-info-btn-popup-title' ).text(),
				classes: [ 'contentstabilization-state-draft-info-popup-title' ]
			} ).$element
		);

		const $list = $( '<ul>' ).addClass( 'contentstabilization-file-list' );
		for ( const text in data ) {
			if ( !data.hasOwnProperty( text ) ) {
				continue;
			}
			$list.append( $( '<li>' ).html( new OO.ui.ButtonWidget( {
				label: text,
				framed: false,
				title: text,
				href: data[ text ],
				classes: [ 'contentstabilization-state-draft-info-popup-link' ]
			} ).$element ) );
		}

		layout.$element.append( $list );
		return layout.$element;
	}
}( mediaWiki, jQuery ) );
