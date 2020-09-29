( function () {
	$( '.mw-anonuserlink' ).after( function () {
		var id, type, ip,
			$revIdAncestor = $( this ).closest( '[data-mw-revid]' ),
			$logIdAncestor = $( this ).closest( '[data-mw-logid]' ),
			button = new OO.ui.PopupButtonWidget( {
				icon: 'info',
				framed: false,
				classes: [ 'ext-ipinfo-button' ],
				popup: {
					padded: true
				}
			} );

		ip = $( this ).text();
		if ( !mw.util.isIPAddress( ip ) ) {
			return '';
		}

		if ( $revIdAncestor.length > 0 ) {
			id = $revIdAncestor.data( 'mwRevid' );
			type = 'revision';
		} else if ( $logIdAncestor.length > 0 ) {
			id = $logIdAncestor.data( 'mwLogid' );
			type = 'log';
		}
		if ( id === undefined ) {
			return '';
		}

		button.once( 'click', function () {
			$.get(
				mw.config.get( 'wgScriptPath' ) +
				'/rest.php/ipinfo/v0/' +
				type + '/' + id
			).then( function ( response ) {
				// TODO: Widget should handle this after T263407
				var i, data, location, source;

				// Array.find is only available from ES6
				for ( i = 0; i < response.info.length; i++ ) {
					if ( response.info[ i ].subject === ip ) {
						data = response.info[ i ];
						break;
					}
				}

				// TODO: display error if data is still undefined - T263409
				if ( data ) {
					location = data.location.map( function ( item ) {
						return item.label;
					} ).join( mw.msg( 'comma-separator' ) );
					source = mw.msg( 'ipinfo-popup-source-mock' );

					button.popup.$body.append(
						$( '<p>' ).addClass( 'ext-ipinfo-popup-location' ).text( location ),
						$( '<p>' ).addClass( 'ext-ipinfo-popup-source' ).text( source )
					);
				}
			} );
		} );

		return button.$element;
	} );
}() );
