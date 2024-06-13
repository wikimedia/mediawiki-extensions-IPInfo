const IpInfoPopupWidget = require( './widget.js' );
const eventLogger = require( '../log.js' );
const postToRestApi = require( '../rest.js' );

mw.hook( 'wikipage.content' ).add( ( $content ) => {
	eventLogger.logIpCopy();

	$content.find( '.mw-anonuserlink' ).after( function () {
		let id, type;
		$( this ).addClass( 'ext-ipinfo-anonuserlink-loaded' );

		const ip = $( this ).text();
		if ( !mw.util.isIPAddress( ip ) ) {
			return '';
		}

		const $revIdAncestor = $( this ).closest( '[data-mw-revid]' );
		const $changedby = $( this ).closest( '.changedby' );
		if ( $revIdAncestor.length > 0 ) {
			id = $revIdAncestor.data( 'mwRevid' );
			type = 'revision';
		} else if ( $changedby.length > 0 ) {
			const $revLines = $( this ).closest( 'table' ).find( '.mw-changeslist-line[data-mw-revid]' );
			$revLines.each( function () {
				const $innerIP = $( this ).find( '.mw-anonuserlink' ).text();
				if ( ip === $innerIP ) {
					id = $( this ).closest( '.mw-changeslist-line [data-mw-revid]' ).attr( 'data-mw-revid' );
					return false;
				}
			} );
			type = 'revision';
		} else {
			const $logIdAncestor = $( this ).closest( '[data-mw-logid]' );
			if ( $logIdAncestor.length > 0 ) {
				id = $logIdAncestor.data( 'mwLogid' );
				type = 'log';
			}
		}
		if ( id === undefined ) {
			return '';
		}

		const button = new OO.ui.PopupButtonWidget( {
			icon: 'info',
			framed: false,
			classes: [ 'ext-ipinfo-button' ]
		} );
		button.once( 'click', () => {
			const popupIpInfoDelayStart = mw.now();
			button.popup.$body.append( new IpInfoPopupWidget(
				postToRestApi( type, id, 'popup' ).then( ( response ) => {
					let i, data;
					const sanitizedIp = mw.util.sanitizeIP( ip );

					// Array.find is only available from ES6
					for ( i = 0; i < response.info.length; i++ ) {
						if ( mw.util.sanitizeIP( response.info[ i ].subject ) === sanitizedIp ) {
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
