( function () {
	/**
	 * Popup Widget
	 *
	 * @class
	 *
	 * @constructor
	 * @param {jQuery.Deferred} info Promise that resolves to an info object.
	 * @param {Object} [config] Configuration options
	 */
	mw.IpInfo.PopupWidget = function ( info, config ) {
		// Parent constructor
		mw.IpInfo.PopupWidget.super.call( this, info, config );
	};

	/* Setup */

	OO.inheritClass( mw.IpInfo.PopupWidget, mw.IpInfo.IpInfoWidget );

	/**
	 * Build HTML to display the IP information.
	 *
	 * @param {Object} info Data returned by the API
	 * @return {Object}
	 */
	mw.IpInfo.PopupWidget.prototype.buildMarkup = function ( info ) {
		var location = ( info.data[ 'ipinfo-source-geoip2' ].location || [] )
			.concat( info.data[ 'ipinfo-source-geoip2' ].country || [] )
			.map( function ( item ) {
				return item.label;
			} ).join( mw.msg( 'comma-separator' ) );

		return $( '<dl>' ).addClass( 'ext-ipinfo-widget-property-properties' ).append(
			this.generatePropertyMarkup( location, 'location' ),
			this.generatePropertyMarkup( info.data[ 'ipinfo-source-geoip2' ].isp, 'isp' ),
			this.generatePropertyMarkup( info.data[ 'ipinfo-source-geoip2' ].asn, 'asn' )
		);
	};
}() );
