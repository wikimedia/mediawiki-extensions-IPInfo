/**
 * IP Info Widget
 *
 * @class
 *
 * @constructor
 * @param {jQuery.Deferred} info Promise that resolves to an info object.
 * @param {Object} [config] Configuration options
 */

var eventLogger = require( '../log.js' );
var ipInfoWidget = function ( info, config ) {
	// Config initialization
	config = $.extend( {
		classes: [
			'ext-ipinfo-widget'
		]
	}, config );

	// Parent constructor
	ipInfoWidget.super.call( this, config );

	// Mixin constructors
	OO.ui.mixin.PendingElement.call( this, config );

	// Set pending element
	// Delay its loading by 100ms see T267237#7620309
	setTimeout( this.setPending.bind( this ), 100 );

	// Promise handler
	info.then(
		this.success.bind( this ),
		this.failure.bind( this )
	).always( this.always.bind( this ) );
};

/* Setup */

OO.inheritClass( ipInfoWidget, OO.ui.Widget );
OO.mixinClass( ipInfoWidget, OO.ui.mixin.PendingElement );

/**
 * Build HTML to display the IP information.
 *
 * @method
 * @abstract
 * @param {Object} info Data returned by the API
 * @return {Object}
 */
ipInfoWidget.prototype.buildMarkup = null;

/**
 * Success callback for the info promise.
 *
 * @param {Object} info
 */
ipInfoWidget.prototype.success = function ( info ) {
	if ( !info ) {
		// The IP address did not match the log or revision ID
		this.displayError( mw.msg( 'ipinfo-widget-error-wrong-ip' ) );
		return;
	}

	this.$element.append( this.buildMarkup( info ) );
};

/**
 * Flag for if the widget has attempted to load the info yet
 */
ipInfoWidget.prototype.done = false;

/**
 * Show progress bar on a delay if data hasn't already loaded
 */
ipInfoWidget.prototype.setPending = function () {
	if ( !this.done ) {
		this.pushPending();
	}
};

/**
 * Failure callback for the info promise.
 *
 * @param {Object} error
 */
ipInfoWidget.prototype.failure = function ( error ) {
	if ( error.responseJSON && error.responseJSON.messageTranslations ) {
		this.displayError( error.responseJSON.messageTranslations[
			mw.config.get( 'wgContentLanguage' )
		] );
	} else {
		this.displayError();
	}
};

/**
 * Display an error, given an error message.
 *
 * @param {string} [label] Error message
 */
ipInfoWidget.prototype.displayError = function ( label ) {
	this.$element.append(
		new OO.ui.MessageWidget( {
			type: 'error',
			label: label || mw.msg( 'ipinfo-widget-error-default' ),
			inline: true,
			classes: [ 'ext-ipinfo-widget-error' ]
		} ).$element
	);
};

/**
 * Callback to call under success or failure.
 *
 * @param {Object} info
 */
ipInfoWidget.prototype.always = function () {
	this.done = true;
	if ( this.isPending() ) {
		this.popPending();
	}
};

/**
 * @param {Array|null|undefined} location (null if missing data;
 *  undefined if access is restricted)
 * @param {Array|null|undefined} country (null if missing data;
 *  undefined if access is restricted)
 * @return {Array|null|undefined}
 */
ipInfoWidget.prototype.getLocation = function ( location, country ) {
	if ( location === undefined && country === undefined ) {
		return undefined;
	}
	var locationData = ( location || [] )
		.concat( country || [] )
		.map( function ( item ) {
			return item.label;
		} ).join( mw.msg( 'comma-separator' ) );
	return locationData.length ? locationData : null;
};

/**
 * @param {Object|null|undefined} proxyType (null if missing data;
 *  undefined if access is restricted)
 * @return {Object|null|undefined}
 */
ipInfoWidget.prototype.getProxyTypes = function ( proxyType ) {
	if ( proxyType === undefined || proxyType === null ) {
		return proxyType;
	}
	// Filter for true values of proxyType
	var proxyTypes = Object.keys( proxyType )
		.filter( function ( proxyTypeKey ) {
			return proxyType[ proxyTypeKey ];
		} );

	// If there are any known proxy types, transform the array into a list of values
	if ( proxyTypes.length === 0 ) {
		return null;
	}

	var $proxyTypes = $( '<ul>' );
	proxyTypes.forEach( function ( type ) {
		// * ipinfo-property-value-proxytype-isanonymousvpn
		// * ipinfo-property-value-proxytype-ispublicproxy
		// * ipinfo-property-value-proxytype-isresidentialproxy
		// * ipinfo-property-value-proxytype-islegitimateproxy
		// * ipinfo-property-value-proxytype-istorexitnode
		// * ipinfo-property-value-proxytype-ishostingprovider
		$proxyTypes.append( $( '<li>' ).text( mw.msg( 'ipinfo-property-value-proxytype-' + type.toLowerCase() ) ) );
	} );
	return $proxyTypes;
};

/**
 * The data should never be missing, as it is not from an external database.
 *
 * @param {number|undefined} numActiveBlocks (undefined if access is restricted)
 * @return {string|undefined}
 */
ipInfoWidget.prototype.getActiveBlocks = function ( numActiveBlocks ) {
	if ( numActiveBlocks === undefined ) {
		return undefined;
	}
	return mw.msg( 'ipinfo-value-active-blocks', numActiveBlocks );
};

/**
 * The data should never be missing, as it is not from an external database.
 *
 * @param {number|undefined} numLocalEdits (undefined if access is restricted)
 * @param {number|undefined} numRecentEdits (undefined if access is restricted)
 * @return {Object|undefined}
 */
ipInfoWidget.prototype.getEdits = function ( numLocalEdits, numRecentEdits ) {
	if ( numLocalEdits === undefined && numRecentEdits === undefined ) {
		return undefined;
	}
	var localEdits = mw.msg( 'ipinfo-value-local-edits', numLocalEdits );

	var $recentEdits = $( '<span>' ).addClass( 'ext-ipinfo-widget-value-recent-edits' )
		.append( mw.msg( 'ipinfo-value-recent-edits', numRecentEdits ) );

	return $( '<span>' ).append(
		localEdits,
		$( '<br>' ),
		$recentEdits
	);
};

/**
 * Generate HTML for a property. All properties are shown regardless if a value exists or not.
 *
 * @param {string} propertyKey
 * @param {Object|string|null|undefined} propertyValue
 * - null indicates missing data
 * - undefined indicates access is restricted
 * @param {string} propertyLabel
 * @param {string} propertyTooltip
 * @return {Object}
 */
ipInfoWidget.prototype.generatePropertyMarkup = function (
	propertyKey,
	propertyValue,
	propertyLabel,
	propertyTooltip
) {
	var $propertyContent = $( '<div>' ).addClass( 'ext-ipinfo-widget-property' ).attr( 'data-property', propertyKey );
	var $propertyLabel = $( '<dt>' ).addClass( 'ext-ipinfo-widget-property-label' ).text( propertyLabel );
	if ( propertyTooltip ) {
		var $propertyTooltip = new OO.ui.PopupButtonWidget( {
			icon: 'info',
			framed: false,
			popup: {
				$content: $( '<span>' ).text( propertyTooltip ),
				padded: true,
				align: 'forwards',
				position: 'above'
			},
			classes: [ 'ext-ipinfo-widget-property-tooltip' ]
		} );
		$propertyLabel.append( $propertyTooltip.$element );

		$propertyTooltip.on( 'click', function () {
			if ( this.popup.isVisible() ) {
				var eventAction;
				switch ( propertyKey ) {
					case 'connectiontype':
						eventAction = 'click_help_connection_method';
						break;
					case 'usertype':
						eventAction = 'click_help_connection_owner';
						break;
					case 'proxytypes':
						eventAction = 'click_help_proxy';
						break;
				}
				eventLogger.log( eventAction, 'infobox' );
			}
		}.bind( $propertyTooltip ) );
	}

	$propertyContent.append(
		$propertyLabel
	);

	if ( propertyValue === null ) {
		$propertyContent.addClass( 'ext-ipinfo-widget-property-no-data' );
		$propertyContent.append(
			$( '<dd>' ).addClass( 'ext-ipinfo-widget-property-value' ).text( mw.msg( 'ipinfo-property-no-data' ) )
		);
	} else if ( propertyValue === undefined ) {
		$propertyContent.addClass( 'ext-ipinfo-widget-property-no-data' );
		$propertyContent.append(
			$( '<dd>' ).addClass( 'ext-ipinfo-widget-property-value' ).text( mw.msg( 'ipinfo-property-no-access' ) )
		);
	} else {
		$propertyContent.append(
			$( '<dd>' ).addClass( 'ext-ipinfo-widget-property-value' ).append( propertyValue )
		);
	}
	return $propertyContent;
};

/**
 * @param {string|null} connectionType
 * @return {string|null}
 */
ipInfoWidget.prototype.getConnectionTypes = function ( connectionType ) {
	if ( connectionType ) {
		// * ipinfo-property-value-connectiontype-cableordsl
		// * ipinfo-property-value-connectiontype-cellular
		// * ipinfo-property-value-connectiontype-corporate
		// * ipinfo-property-value-connectiontype-dialup
		return mw.msg( 'ipinfo-property-value-connectiontype-' + connectionType.toLowerCase().replace( /\//g, 'or' ) );
	}
	return null;
};

/**
 * @param {string|null} userType
 * @return {string|null}
 */
ipInfoWidget.prototype.getUserTypes = function ( userType ) {
	if ( userType ) {
		// * ipinfo-property-value-usertype-college
		// * ipinfo-property-value-usertype-residential
		// * ipinfo-property-value-usertype-searchenginespider
		// * ipinfo-property-value-usertype-contentdeliverynetwork
		// * ipinfo-property-value-usertype-consumerprivacynetwork
		// * ipinfo-property-value-usertype-business
		// * ipinfo-property-value-usertype-cafe
		// * ipinfo-property-value-usertype-cellular
		// * ipinfo-property-value-usertype-dialup
		// * ipinfo-property-value-usertype-government
		// * ipinfo-property-value-usertype-hosting
		// * ipinfo-property-value-usertype-library
		// * ipinfo-property-value-usertype-military
		// * ipinfo-property-value-usertype-router
		// * ipinfo-property-value-usertype-school
		// * ipinfo-property-value-usertype-traveler
		return mw.msg( 'ipinfo-property-value-usertype-' + userType.replace( /_/g, '' ) );
	}
	return null;
};

module.exports = ipInfoWidget;
