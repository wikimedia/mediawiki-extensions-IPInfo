( function () {
	var ip = mw.config.get( 'wgIPInfoTarget' ),
		revId, ipPanel, ipInfoBox;
	if ( !ip ) {
		return;
	}

	revId = $( '.mw-contributions-list [data-mw-revid]' ).first().attr( 'data-mw-revid' );
	if ( !revId ) {
		return;
	}

	ipPanel = new OO.ui.PanelLayout( {
		$content: new mw.IpInfo.IpInfoWidget(
			$.get(
				mw.config.get( 'wgScriptPath' ) +
					'/rest.php/ipinfo/v0/revision/' + revId
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
		).$element,
		padded: true,
		framed: true
	} );
	ipInfoBox = new OO.ui.StackLayout( {
		items: [ ipPanel ],
		continuous: true,
		expanded: false
	} );
	$( '#mw-content-text' ).prepend( ipInfoBox.$element );
}() );
