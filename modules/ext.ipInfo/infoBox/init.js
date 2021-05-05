( function () {
	var ip = mw.config.get( 'wgIPInfoTarget' ),
		revId, ipPanelWidget;
	if ( !ip ) {
		return;
	}

	revId = $( '.mw-contributions-list [data-mw-revid]' ).first().attr( 'data-mw-revid' );
	if ( !revId ) {
		return;
	}

	ipPanelWidget = new mw.IpInfo.IpInfoWidget(
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
		} ),
		[
			'location',
			'isp',
			'asn',
			'organization'
		]
	);

	$( '.ext-ipinfo-collapsible-layout .mw-collapsible-content' ).append( ipPanelWidget.$element );
}() );
