( function () {
	/**
	 * Info Box Widget
	 *
	 * @class
	 *
	 * @constructor
	 * @param {jQuery.Deferred} info Promise that resolves to an info object.
	 * @param {Object} [config] Configuration options
	 */
	mw.IpInfo.InfoBoxWidget = function ( info, config ) {
		// Parent constructor
		mw.IpInfo.InfoBoxWidget.super.call( this, info, config );
	};

	/* Setup */

	OO.inheritClass( mw.IpInfo.InfoBoxWidget, mw.IpInfo.IpInfoWidget );

	/**
	 * Build HTML to display the IP information.
	 *
	 * @param {Object} info Data returned by the API
	 * @return {Object}
	 */
	mw.IpInfo.InfoBoxWidget.prototype.buildMarkup = function ( info ) {
		var location = ( info.data[ 'ipinfo-source-geoip2' ].location || [] )
			.concat( info.data[ 'ipinfo-source-geoip2' ].country || [] )
			.map( function ( item ) {
				return item.label;
			} ).join( mw.msg( 'comma-separator' ) );

		return $( '<dl>' ).addClass( 'ext-ipinfo-widget-properties' )
			.append(
				$( '<div>' ).addClass( 'ext-ipinfo-widget-properties-col' ).append(
					this.generatePropertyMarkup( location, 'location' ),
					this.generatePropertyMarkup( info.data[ 'ipinfo-source-geoip2' ].isp, 'isp' ),
					this.generatePropertyMarkup( info.data[ 'ipinfo-source-geoip2' ].asn, 'asn' ),
					this.generatePropertyMarkup( info.data[ 'ipinfo-source-geoip2' ].organization, 'organization' )
				)
			).append(
				$( '<div>' ).addClass( 'ext-ipinfo-widget-properties-col' ).append(
					$( '<div>' ).addClass( 'ext-ipinfo-widget-property-source' ).text( mw.msg( 'ipinfo-source-geoip2' ) )
				)
			);
	};
}() );
