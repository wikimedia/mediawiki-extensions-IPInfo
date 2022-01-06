( function () {
	/**
	 * Infobox Widget
	 *
	 * @class
	 *
	 * @constructor
	 * @param {jQuery.Deferred} info Promise that resolves to an info object.
	 * @param {Object} [config] Configuration options
	 */
	mw.IpInfo.InfoboxWidget = function ( info, config ) {
		// Parent constructor
		mw.IpInfo.InfoboxWidget.super.call( this, info, config );
	};

	/* Setup */

	OO.inheritClass( mw.IpInfo.InfoboxWidget, mw.IpInfo.IpInfoWidget );

	/**
	 * Build HTML to display the IP information.
	 *
	 * @param {Object} info Data returned by the API
	 * @return {Object}
	 */
	mw.IpInfo.InfoboxWidget.prototype.buildMarkup = function ( info ) {
		var location = ( info.data[ 'ipinfo-source-geoip2' ].location || [] )
			.concat( info.data[ 'ipinfo-source-geoip2' ].country || [] )
			.map( function ( item ) {
				return item.label;
			} ).join( mw.msg( 'comma-separator' ) );

		var activeBlocks = mw.msg( 'ipinfo-value-active-blocks', info.data[ 'ipinfo-source-block' ].numActiveBlocks );

		var localEdits = mw.msg( 'ipinfo-value-local-edits', info.data[ 'ipinfo-source-contributions' ].numLocalEdits );

		var $recentEdits = $( '<span>' ).addClass( 'ext-ipinfo-widget-value-recent-edits' )
			.append( mw.msg( 'ipinfo-value-recent-edits', info.data[ 'ipinfo-source-contributions' ].numRecentEdits ) );

		var $edits = $( '<span>' ).append(
			localEdits,
			$( '<br>' ),
			$recentEdits
		);

		return $( '<dl>' ).addClass( 'ext-ipinfo-widget-properties' )
			.append(
				$( '<div>' ).addClass( 'ext-ipinfo-widget-properties-col' ).append(
					this.generatePropertyMarkup( location, mw.msg( 'ipinfo-property-label-location' ) ),
					this.generatePropertyMarkup( info.data[ 'ipinfo-source-geoip2' ].isp, mw.msg( 'ipinfo-property-label-isp' ) ),
					this.generatePropertyMarkup( info.data[ 'ipinfo-source-geoip2' ].asn, mw.msg( 'ipinfo-property-label-asn' ) ),
					this.generatePropertyMarkup( info.data[ 'ipinfo-source-geoip2' ].organization, mw.msg( 'ipinfo-property-label-organization' ) )
				)
			).append(
				$( '<div>' ).addClass( 'ext-ipinfo-widget-properties-col' ).append(
					this.generatePropertyMarkup( info.data[ 'ipinfo-source-geoip2' ].connectionType, mw.msg( 'ipinfo-property-label-connectiontype' ), mw.msg( 'ipinfo-property-tooltip-connectiontype' ) ),
					// Only show userType if it's not the same as connectionType
					this.generatePropertyMarkup(
						info.data[ 'ipinfo-source-geoip2' ].userType !== info.data[ 'ipinfo-source-geoip2' ].connectionType ?
							info.data[ 'ipinfo-source-geoip2' ].userType : null,
						mw.msg( 'ipinfo-property-label-usertype' ),
						mw.msg( 'ipinfo-property-tooltip-usertype' ) )
				)
			).append(
				$( '<div>' ).addClass( 'ext-ipinfo-widget-properties-col' ).append(
					this.generatePropertyMarkup( activeBlocks, mw.msg( 'ipinfo-property-label-active-blocks' ) ),
					this.generatePropertyMarkup( $edits, mw.msg( 'ipinfo-property-label-edits' ) ),
					$( '<div>' ).addClass( 'ext-ipinfo-widget-property-source' ).text( mw.msg( 'ipinfo-source-geoip2' ) )
				)
			);
	};
}() );
