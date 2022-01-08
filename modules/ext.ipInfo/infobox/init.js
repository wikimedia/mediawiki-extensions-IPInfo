var IpInfoInfoboxWidget = require( './widget.js' );
var ip = mw.config.get( 'wgIPInfoTarget' ),
	api = new mw.Api(),
	saveCollapsibleUserOption, ipPanelWidget,
	loadIpInfo, hasUseAgreement, agreementFormWidget,
	isExpanded, timerStart;

if ( ip ) {
	isExpanded = $( '.ext-ipinfo-panel-layout .mw-collapsible-toggle' ).attr( 'aria-expanded' ) === 'true';
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

	loadIpInfo = function ( targetIp ) {
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

		ipPanelWidget = new IpInfoInfoboxWidget(
			$.get(
				mw.config.get( 'wgScriptPath' ) +
					'/rest.php/ipinfo/v0/revision/' + revId
			).then( function ( response ) {
				var i, data;
				// Array.find is only available from ES6
				for ( i = 0; i < response.info.length; i++ ) {
					if ( response.info[ i ].subject === targetIp ) {
						data = response.info[ i ];
						break;
					}
				}
				if ( isExpanded ) {
					mw.track( 'timing.MediaWiki.ipinfo_accordion_delay', mw.now() - timerStart );
				}
				return data;
			} )
		);

		$( '.ext-ipinfo-collapsible-layout .mw-collapsible-content' ).append( ipPanelWidget.$element );
	};

	// Check for user's ipinfo-use-agreement option
	hasUseAgreement = !!mw.user.options.get( 'ipinfo-use-agreement' );

	// Show the form to agree to the terms of use instead of ip info
	if ( hasUseAgreement ) {
		timerStart = mw.now();
		// If already agreed to ipinfo-use-agreement, can load ip info on page load
		loadIpInfo( ip );
	} else {
		agreementFormWidget = new OO.ui.FormLayout( {
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
		$( '.ipinfo-use-agreement-form' ).on( 'submit', function ( e ) {
			e.preventDefault();
			api.saveOption( 'ipinfo-use-agreement', '1' )
				.always( function () {
					$( '.ipinfo-use-agreement-form' ).remove();
				} ).then( function () {
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
	}
}
