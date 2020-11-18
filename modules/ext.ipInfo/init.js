( function () {
	$( '.mw-anonuserlink' ).after( function () {
		var id, type, ip, $revIdAncestor, $logIdAncestor, button;
		ip = $( this ).text();
		if ( !mw.util.isIPAddress( ip ) ) {
			return '';
		}

		$revIdAncestor = $( this ).closest( '[data-mw-revid]' );
		if ( $revIdAncestor.length > 0 ) {
			id = $revIdAncestor.data( 'mwRevid' );
			type = 'revision';
		} else {
			$logIdAncestor = $( this ).closest( '[data-mw-logid]' );
			if ( $logIdAncestor.length > 0 ) {
				id = $logIdAncestor.data( 'mwLogid' );
				type = 'log';
			}
		}
		if ( id === undefined ) {
			return '';
		}

		button = new OO.ui.PopupButtonWidget( {
			icon: 'info',
			framed: false,
			classes: [ 'ext-ipinfo-button' ]
		} );
		button.once( 'click', function () {
			button.popup.$body.append( new mw.IpInfo.IpInfoWidget(
				$.get(
					mw.config.get( 'wgScriptPath' ) +
						'/rest.php/ipinfo/v0/' +
						type + '/' + id
				).then( function ( response ) {
					var i, data;

					// Array.find is only available from ES6
					for ( i = 0; i < response.info.length; i++ ) {
						if ( response.info[ i ].subject === ip ) {
							data = response.info[ i ];
							break;
						}
					}

					return data;
				} )
			).$element );
		} );

		return button.$element;
	} );
}() );
