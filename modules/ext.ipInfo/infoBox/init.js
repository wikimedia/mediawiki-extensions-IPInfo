( function () {
	var ip = mw.config.get( 'wgIPInfoTarget' ),
		revId, ipPanel, $ipPanelContent, ipPanelToggle, ipPanelWidget, ipInfoBox;
	if ( !ip ) {
		return;
	}

	revId = $( '.mw-contributions-list [data-mw-revid]' ).first().attr( 'data-mw-revid' );
	if ( !revId ) {
		return;
	}

	$ipPanelContent = $( '<div>' );

	ipPanelToggle = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'expand',
		classes: [ 'ext-ipinfo-button-collapse' ],
		label: mw.msg( 'ipinfo-infobox-title' )
	} );
	ipPanelToggle.on( 'click', function () {
		if ( ipPanelWidget.visible ) {
			ipPanelWidget.toggle( false );
			ipPanelToggle.setIcon( 'expand' );
		} else {
			ipPanelWidget.toggle( true );
			ipPanelToggle.setIcon( 'collapse' );
		}
	} );

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
	ipPanelWidget.toggle( false );

	$ipPanelContent.append( ipPanelToggle.$element );
	$ipPanelContent.append( ipPanelWidget.$element );

	ipPanel = new OO.ui.PanelLayout( {
		$content: $ipPanelContent,
		padded: true,
		framed: true
	} );
	ipInfoBox = new OO.ui.StackLayout( {
		items: [ ipPanel ],
		continuous: true,
		expanded: false,
		classes: [ 'ext-ipinfo-infobox' ]
	} );
	$( '#mw-content-text' ).prepend( ipInfoBox.$element );
}() );
