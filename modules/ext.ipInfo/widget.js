( function () {
	mw.IpInfo = mw.IpInfo || {};

	/**
	 * IP Info Widget
	 *
	 * @class
	 *
	 * @constructor
	 * @param {jQuery.Deferred} info Promise that resolves to an info object.
	 * @param {Object} [config] Configuration options
	 */
	mw.IpInfo.IpInfoWidget = function ( info, config ) {
		// Config initialization
		config = $.extend( {
			classes: [
				'ext-ipinfo-widget'
			]
		}, config );

		// Parent constructor
		mw.IpInfo.IpInfoWidget.super.call( this, config );

		// Mixin constructors
		OO.ui.mixin.PendingElement.call( this, config );

		// Set pending element.
		this.pushPending();

		// Promise handler
		info.then(
			this.success.bind( this ),
			this.failure.bind( this )
		).always( this.always.bind( this ) );
	};

	/* Setup */

	OO.inheritClass( mw.IpInfo.IpInfoWidget, OO.ui.Widget );
	OO.mixinClass( mw.IpInfo.IpInfoWidget, OO.ui.mixin.PendingElement );

	/**
	 * Success callback for the info promise.
	 *
	 * @param {Object} info
	 */
	mw.IpInfo.IpInfoWidget.prototype.success = function ( info ) {
		var location, source;

		if ( info ) {
			location = info.location.map( function ( item ) {
				return item.label;
			} ).join( mw.msg( 'comma-separator' ) );
			source = mw.msg( 'ipinfo-widget-source-mock' );

			this.$element.append(
				$( '<p>' ).addClass( 'ext-ipinfo-widget-location' ).text( location ),
				$( '<p>' ).addClass( 'ext-ipinfo-widget-source' ).text( source )
			);
		} else {
			// The IP address did not match the log or revision ID
			this.displayError( mw.msg( 'ipinfo-widget-error-wrong-ip' ) );
		}
	};

	/**
	 * Failure callback for the info promise.
	 *
	 * @param {Object} error
	 */
	mw.IpInfo.IpInfoWidget.prototype.failure = function ( error ) {
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
	mw.IpInfo.IpInfoWidget.prototype.displayError = function ( label ) {
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
	mw.IpInfo.IpInfoWidget.prototype.always = function () {
		this.popPending();
	};
}() );
