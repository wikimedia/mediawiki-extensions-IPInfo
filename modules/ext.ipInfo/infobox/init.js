// Disable this rule because jQuery.makeCollapsible stores state in the class
/* eslint-disable no-jquery/no-class-state */

const IpInfoInfoboxWidget = require( './widget.js' );
const relevantUserName = mw.util.prettifyIP( mw.config.get( 'wgRelevantUserName' ) ),
	api = new mw.Api(),
	eventLogger = require( '../log.js' ),
	postToRestApi = require( '../rest.js' );
let viewedAgreement = false,
	timerStart;

function initInfoboxWidget() {
	if ( !relevantUserName ) {
		return;
	}

	eventLogger.logIpCopy();

	const saveCollapsibleUserOption = function ( e ) {
		// Only trigger on enter and space keypresses
		if ( e.type === 'keypress' && e.which !== 13 && e.which !== 32 ) {
			return;
		}

		// Store user preference in mw.storage; if it's removed from the codebase,
		// it'll need to be cleaned up via mw.storage.remove
		if ( !$( this ).closest( '.mw-collapsible' ).hasClass( 'mw-collapsed' ) ) {
			mw.storage.set( 'mw-ipinfo-infobox-expanded', 1 );
			// Log when the infobox is manually expanded
			eventLogger.log( 'expand', 'infobox' );
		} else {
			mw.storage.set( 'mw-ipinfo-infobox-expanded', 0 );
			// Log when the infobox is manually collasped
			eventLogger.log( 'collapse', 'infobox' );
		}
	};

	// Ensure collapsible panel event handlers are added first, so that the collapsible panel
	// has the correct classes when the handlers added here run. (It would be better to have
	// a collapsible panel that modelled its collapsed state, so we could check that instead.)
	$( '.mw-collapsible' ).makeCollapsible();

	// Determine if infobox should be expanded on load
	// Do this before we attach the click handler so we can use click() to expand
	// the infobox without setting the user preference
	const hasOpenInfoboxQueryParam = new URLSearchParams( window.location.search ).get( 'openInfobox' ) === 'true';
	if ( $( '.ext-ipinfo-collapsible-layout' ).hasClass( 'mw-collapsed' ) &&
		( hasOpenInfoboxQueryParam ||
		!!Number( mw.storage.get( 'mw-ipinfo-infobox-expanded' ) ) ) ) {
		$( '.ext-ipinfo-panel-layout .mw-collapsible-toggle' ).trigger( 'click' );
	}

	// Watch for collapse/expand events and save that state to a user option
	$( '.ext-ipinfo-panel-layout .mw-collapsible-toggle' ).on( 'click keypress', saveCollapsibleUserOption );

	const loadIpInfo = function ( targetName ) {
		const revId = $( '.mw-contributions-list [data-mw-revid]' ).first().attr( 'data-mw-revid' );
		if ( !revId ) {
			$( '.ext-ipinfo-collapsible-layout' ).addClass( 'ext-ipinfo-contains-error' );
			$( '.ext-ipinfo-collapsible-layout .mw-collapsible-content' ).append(
				new OO.ui.MessageWidget( {
					type: 'error',
					label: mw.msg( 'ipinfo-widget-error-ip-no-edits' )
				} ).$element
			);
			return;
		}

		const endpoint = mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Contributions' ?
			'revision' : 'archivedrevision';

		const ipPanelWidget = new IpInfoInfoboxWidget(
			postToRestApi( endpoint, revId, 'infobox' ).then( ( response ) => {
				let i, data;
				const sanitizedTargetName = mw.util.sanitizeIP( targetName );
				// Array.find is only available from ES6
				for ( i = 0; i < response.info.length; i++ ) {
					if ( mw.util.sanitizeIP( response.info[ i ].subject ) === sanitizedTargetName ) {
						data = response.info[ i ];
						break;
					}
				}

				if ( data ) {
					const context = hasOpenInfoboxQueryParam ? 'popup' : 'infobox';

					const hasGeoData = Object.keys( data.data[ 'ipinfo-source-geoip2' ] )
						.some( ( k ) => data.data[ 'ipinfo-source-geoip2' ][ k ] !== null );
					const hasIpoidData = Object.keys( data.data[ 'ipinfo-source-ipoid' ] )
						.some( ( k ) => data.data[ 'ipinfo-source-ipoid' ][ k ] !== null );

					const dataSources = [];
					if ( hasGeoData ) {
						dataSources.push( 'maxmind' );
					}

					if ( hasIpoidData ) {
						dataSources.push( 'spur' );
					}

					// Track what data sources provided data for this lookup (T356105).
					// Refer to the IPInfo EventLogging schema for allowed values.
					const params = {
						// eslint-disable-next-line camelcase
						event_ip_data_sources: dataSources
					};

					eventLogger.log( 'open_infobox', context, params );
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
	const logUnloadPageWithoutAcceptingAgreement = function () {
		if ( viewedAgreement ) {
			eventLogger.log( 'close_disclaimer', 'infobox' );
		}
	};

	const loadUseAgreement = function () {
		// Show the form to agree to the terms of use instead of ip info
		const agreementFormWidget = new OO.ui.FormLayout( {
			classes: [ 'ipinfo-use-agreement-form' ],
			content: [
				new OO.ui.Element( {
					content: [
						new OO.ui.HtmlSnippet(
							mw.message( 'ipinfo-infobox-use-terms' ).parse()
						)
					]
				} ),
				new OO.ui.FieldLayout(
					new OO.ui.CheckboxInputWidget( {
						value: 'ipinfo-use-agreement',
						required: true
					} ),
					{
						label: new OO.ui.HtmlSnippet(
							mw.message( 'ipinfo-preference-use-agreement' ).parse()
						),
						align: 'inline'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonInputWidget( {
						type: 'submit',
						name: 'submit-agreement',
						label: new OO.ui.HtmlSnippet(
							mw.message( 'ipinfo-infobox-submit-agreement' ).parse()
						),
						flags: [
							'primary',
							'progressive'
						]
					} ),
					{
						align: 'top',
						help: new OO.ui.HtmlSnippet(
							mw.message( 'ipinfo-infobox-disable-instructions' ).parse()
						),
						helpInline: true
					}
				)
			]
		} );
		$( '.ext-ipinfo-collapsible-layout .mw-collapsible-content' ).append( agreementFormWidget.$element );

		// Log that we're showing the use agreement form
		viewedAgreement = true;
		eventLogger.log( 'init_disclaimer', 'infobox' );

		$( '.ipinfo-use-agreement-form' ).on( 'submit', ( e ) => {
			e.preventDefault();
			api.saveOption( 'ipinfo-use-agreement', '1' )
				.always( () => {
					$( '.ipinfo-use-agreement-form' ).remove();
				} ).then( () => {
					// Log that the use agreement was accepted
					eventLogger.log( 'accept_disclaimer', 'infobox' );

					// The user has successfully agreed; unbind the unload listener
					$( window ).off( 'beforeunload', logUnloadPageWithoutAcceptingAgreement );

					// Success - show ip info
					$( '.ipinfo-use-agreement-form' ).remove();
					loadIpInfo( relevantUserName );
				} ).catch( ( error ) => {
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
	if ( !$( '.ext-ipinfo-collapsible-layout' ).hasClass( 'mw-collapsed' ) ) {
		if ( !mw.user.options.get( 'ipinfo-use-agreement' ) ) {
			loadUseAgreement();
		} else {
			timerStart = mw.now();
			loadIpInfo( relevantUserName );
		}
	} else {
		// Watch for the first expand command, load content, and unbind listener
		const onFirstInfoboxExpand = function ( e ) {
			// Only trigger on enter and space keypresses
			if ( e.type === 'keypress' && e.which !== 13 && e.which !== 32 ) {
				return;
			}
			// Only load if expanding the infobox
			if ( !$( this ).closest( '.mw-collapsible' ).hasClass( 'mw-collapsed' ) ) {
				if ( !mw.user.options.get( 'ipinfo-use-agreement' ) ) {
					loadUseAgreement();
				} else {
					timerStart = mw.now();
					loadIpInfo( relevantUserName );
				}
			}

			$( '.ext-ipinfo-panel-layout .mw-collapsible-toggle' ).off( 'click keypress', onFirstInfoboxExpand );
		};
		$( '.ext-ipinfo-panel-layout .mw-collapsible-toggle' ).on( 'click keypress', onFirstInfoboxExpand );
	}
}

$( initInfoboxWidget );
