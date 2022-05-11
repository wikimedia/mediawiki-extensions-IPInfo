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
 * Generate HTML for a property. All properties are shown regardless if a value exists or not.
 *
 * @param {string} propertyKey
 * @param {Object} propertyValue
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
				align: 'backwards',
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

	if ( propertyValue !== null && propertyValue !== undefined ) {
		$propertyContent.append(
			$( '<dd>' ).addClass( 'ext-ipinfo-widget-property-value' ).append( propertyValue )
		);
	} else {
		$propertyContent.addClass( 'ext-ipinfo-widget-property-no-data' );
		$propertyContent.append(
			$( '<dd>' ).addClass( 'ext-ipinfo-widget-property-value' ).text( mw.msg( 'ipinfo-property-no-data' ) )
		);
	}
	return $propertyContent;
};

module.exports = ipInfoWidget;
