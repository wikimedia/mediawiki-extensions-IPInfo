var IpInfoPopupWidget = require( './widget.js' );
var eventLogger = require( '../log.js' );

mw.hook( 'wikipage.content' ).add( function ( $content ) {
	eventLogger.logIpCopy();

	$content.find( '.mw-anonuserlink' ).after( function () {
		var id, type;
		$( this ).addClass( 'ext-ipinfo-anonuserlink-loaded' );

		var ip = $( this ).text();
		if ( !mw.util.isIPAddress( ip ) ) {
			return '';
		}

		var $revIdAncestor = $( this ).closest( '[data-mw-revid]' );
		var $changedby = $( this ).closest( '.changedby' );
		if ( $revIdAncestor.length > 0 ) {
			id = $revIdAncestor.data( 'mwRevid' );
			type = 'revision';
		} else if ( $changedby.length > 0 ) {
			var $revLines = $( this ).closest( 'table' ).find( '.mw-changeslist-line[data-mw-revid]' );
			$revLines.each( function () {
				var $innerIP = $( this ).find( '.mw-anonuserlink' ).text();
				if ( ip === $innerIP ) {
					id = $( this ).closest( '.mw-changeslist-line [data-mw-revid]' ).attr( 'data-mw-revid' );
					return false;
				}
			} );
			type = 'revision';
		} else {
			var $logIdAncestor = $( this ).closest( '[data-mw-logid]' );
			if ( $logIdAncestor.length > 0 ) {
				id = $logIdAncestor.data( 'mwLogid' );
				type = 'log';
			}
		}
		if ( id === undefined ) {
			return '';
		}

		var button = new OO.ui.PopupButtonWidget( {
			icon: 'info',
			framed: false,
			classes: [ 'ext-ipinfo-button' ]
		} );
		button.once( 'click', function () {
			var popupIpInfoDelayStart = mw.now();
			button.popup.$body.append( new IpInfoPopupWidget(

				$.get(
					mw.config.get( 'wgScriptPath' ) +
						'/rest.php/ipinfo/v0/' +
						type + '/' + id + '?dataContext=popup'
				).then( function ( response ) {
					var i, data;

					// Array.find is only available from ES6
					for ( i = 0; i < response.info.length; i++ ) {
						if ( response.info[ i ].subject === mw.util.prettifyIP( ip ) ) {
							data = response.info[ i ];
							break;
						}
					}
					mw.track( 'timing.MediaWiki.ipinfo_popup_delay', mw.now() - popupIpInfoDelayStart );
					eventLogger.log( 'open_popup', 'page' );

					return data;
				} )
			).$element );
		} );

		return button.$element;
	} );
} );
