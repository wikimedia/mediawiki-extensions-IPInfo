var IpInfoInfoboxWidget = require( './widget.js' );
var ip = mw.config.get( 'wgIPInfoTarget' ),
	api = new mw.Api(),
	viewedAgreement = false,
	timerStart,
	log = require( '../log.js' );

if ( ip ) {
	var saveCollapsibleUserOption = function ( e ) {
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

	var loadIpInfo = function ( targetIp ) {
		var revId = $( '.mw-contributions-list [data-mw-revid]' ).first().attr( 'data-mw-revid' );
		if ( !revId ) {
			$( '.ext-ipinfo-collapsible-layout .mw-collapsible-content' ).append(
				new OO.ui.MessageWidget( {
					type: 'error',
					label: mw.msg( 'ipinfo-widget-error-ip-no-edits' )
				} ).$element
			);
			return;
		}

		var ipPanelWidget = new IpInfoInfoboxWidget(
			$.get(
				mw.config.get( 'wgScriptPath' ) +
					'/rest.php/ipinfo/v0/revision/' + revId + '?dataContext=infobox'
			).then( function ( response ) {
				var i, data;
				// Array.find is only available from ES6
				for ( i = 0; i < response.info.length; i++ ) {
					if ( response.info[ i ].subject === targetIp ) {
						data = response.info[ i ];
						break;
					}
				}
				mw.track( 'timing.MediaWiki.ipinfo_infobox_delay', mw.now() - timerStart );
				return data;
			} )
		);

		$( '.ext-ipinfo-collapsible-layout .mw-collapsible-content' ).append( ipPanelWidget.$element );
	};

	// Logging event for navigating away from the agreement as a function so that
	// it can be unbound in the case the user has accepted the agreement and we no longer
	// need to listen for this event
	var logUnloadPageWithoutAcceptingAgreement = function () {
		if ( viewedAgreement ) {
			log( 'close_disclaimer', 'infobox' );
		}
	};

	var loadUseAgreement = function () {
		// Show the form to agree to the terms of use instead of ip info
		var agreementFormWidget = new OO.ui.FormLayout( {
			classes: [ 'ipinfo-use-agreement-form' ],
			content: [
				new OO.ui.Element( {
					content: [
						mw.msg( 'ipinfo-infobox-use-terms' )
					]
				} ),
				new OO.ui.FieldLayout(
					new OO.ui.CheckboxInputWidget( {
						value: 'ipinfo-use-agreement',
						required: true
					} ),
					{ label: mw.msg( 'ipinfo-preference-use-agreement' ), align: 'inline' }
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonInputWidget( {
						type: 'submit',
						name: 'submit-agreement',
						label: mw.msg( 'ipinfo-infobox-submit-agreement' ),
						flags: [
							'primary',
							'progressive'
						]
					} ), {
						align: 'top',
						help: new OO.ui.HtmlSnippet( mw.message( 'ipinfo-infobox-disable-instructions' ).parse() ),
						helpInline: true
					}
				)
			]
		} );
		$( '.ext-ipinfo-collapsible-layout .mw-collapsible-content' ).append( agreementFormWidget.$element );

		// Log that we're showing the use agreement form
		viewedAgreement = true;
		log( 'init_disclaimer', 'infobox' );

		$( '.ipinfo-use-agreement-form' ).on( 'submit', function ( e ) {
			e.preventDefault();
			api.saveOption( 'ipinfo-use-agreement', '1' )
				.always( function () {
					$( '.ipinfo-use-agreement-form' ).remove();
				} ).then( function () {
					// Log that the use agreement was accepted
					log( 'accept_disclaimer', 'infobox' );

					// The user has successfully agreed; unbind the unload listener
					$( window ).off( 'beforeunload', logUnloadPageWithoutAcceptingAgreement );

					// Success - show ip info
					$( '.ipinfo-use-agreement-form' ).remove();
					loadIpInfo( ip );
				} ).catch( function ( error ) {
					// Fail state - show an error
					$( '.ext-ipinfo-collapsible-layout .mw-collapsible-content' ).append(
						new OO.ui.MessageWidget( {
							type: 'error',
							label: mw.msg( 'ipinfo-use-agreement-submit-error', error )
						} ).$element
					);
				} );
		} );

		// If we load the form, also watch for the user leaving the page without agreeing (T296477)
		$( window ).on( 'beforeunload', logUnloadPageWithoutAcceptingAgreement );
	};

	// Auto-load either the form or the ip info if the infobox is expanded on-load
	if ( $( '.ext-ipinfo-panel-layout .mw-collapsible-toggle' ).attr( 'aria-expanded' ) === 'true' ) {
		if ( !mw.user.options.get( 'ipinfo-use-agreement' ) ) {
			loadUseAgreement();
		} else {
			timerStart = mw.now();
			loadIpInfo( ip );
		}
	} else {
		// Watch for the first expand command, load content, and unbind listener
		var onFirstInfoboxExpand = function ( e ) {
			// Only trigger on enter and space keypresses
			if ( e.type === 'keypress' && e.which !== 13 && e.which !== 32 ) {
				return;
			}
			// Only load if expanding the infobox
			if ( $( this ).attr( 'aria-expanded' ) === 'true' ) {
				if ( !mw.user.options.get( 'ipinfo-use-agreement' ) ) {
					loadUseAgreement();
				} else {
					timerStart = mw.now();
					loadIpInfo( ip );
				}
			}

			$( '.ext-ipinfo-panel-layout .mw-collapsible-toggle' ).off( 'click keypress', onFirstInfoboxExpand );
		};
		$( '.ext-ipinfo-panel-layout .mw-collapsible-toggle' ).on( 'click keypress', onFirstInfoboxExpand );
	}
}
