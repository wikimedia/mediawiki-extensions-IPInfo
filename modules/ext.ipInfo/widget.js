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

		// @TODO: display error if data is still undefined - T263409
		if ( !info ) {
			return;
		}

		location = info.location.map( function ( item ) {
			return item.label;
		} ).join( mw.msg( 'comma-separator' ) );
		source = mw.msg( 'ipinfo-widget-source-mock' );

		this.$element.append(
			$( '<p>' ).addClass( 'ext-ipinfo-widget-location' ).text( location ),
			$( '<p>' ).addClass( 'ext-ipinfo-widget-source' ).text( source )
		);
	};

	/**
	 * Failure callback for the info promise.
	 *
	 * @param {Object} info
	 */
	mw.IpInfo.IpInfoWidget.prototype.failure = function ( /* error */ ) {
		// @TODO Display error
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
