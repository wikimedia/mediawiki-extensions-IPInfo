var IpInfoPopupWidget = require( './widget.js' );
mw.hook( 'wikipage.content' ).add( function ( $content ) {
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
			classes: [ 'ext-ipinfo-button' ],
			popup: {
				padded: true
			}
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
						if ( response.info[ i ].subject === ip ) {
							data = response.info[ i ];
							break;
						}
					}
					mw.track( 'timing.MediaWiki.ipinfo_popup_delay', mw.now() - popupIpInfoDelayStart );

					var specialPage = mw.config.get( 'wgCanonicalSpecialPageName' );
					switch ( specialPage ) {
						case 'Log':
							specialPage = 'special_log';
							break;
						case 'Recentchanges':
							specialPage = 'special_recentchanges';
							break;
						case false:
							specialPage = 'action_history';
							break;
					}

					var popupInfoClick = {
						/* eslint-disable camelcase */
						$schema: '/analytics/mediawiki/ipinfo_interaction/1.0.0',
						event_action: 'open_popup',
						event_context: 'page',
						event_source: specialPage,
						user_edit_bucket: mw.config.get( 'wgUserEditCountBucket' ),
						user_groups: mw.config.get( 'wgUserGroups', [] ).join( '|' )
						/* eslint-enable camelcase */
					};

					mw.track( 'ipinfo.event', popupInfoClick );
					mw.trackSubscribe( 'ipinfo.event', function ( topic, eventData ) {
						if ( mw.eventLog ) {
							mw.eventLog.submit( 'mediawiki.ipinfo_interaction', eventData );
						}
					} );
					return data;
				} )
			).$element );
		} );

		return button.$element;
	} );
} );
