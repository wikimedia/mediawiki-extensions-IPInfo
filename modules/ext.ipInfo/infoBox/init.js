( function () {
	var ip = mw.config.get( 'wgIPInfoTarget' ),
		api = new mw.Api(),
		saveCollapsibleUserOption, revId, ipPanelWidget;

	saveCollapsibleUserOption = function ( e ) {
		// Only trigger on enter and space keypresses
		if ( e.type === 'keypress' && e.which !== 13 && e.which !== 32 ) {
			return;
		}
		if ( $( this ).attr( 'aria-expanded' ) === 'true' ) {
			api.saveOption( 'ipinfo-infobox-expanded', 1 );
		} else {
			api.saveOption( 'ipinfo-infobox-expanded', 0 );
		}
	};

	// Watch for collapse/expand events and save that state to a user option
	$( '.ext-ipinfo-panel-layout .mw-collapsible-toggle' ).on( 'click keypress', saveCollapsibleUserOption );

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
		{
			'ipinfo-source-geoip2': [
				'location',
				'isp',
				'asn',
				'organization'
			]
		}
	);

	$( '.ext-ipinfo-collapsible-layout .mw-collapsible-content' ).append( ipPanelWidget.$element );
}() );
