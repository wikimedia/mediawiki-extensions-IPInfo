var ipInfoWidget = require( '../widget.js' );

/**
 * Infobox Widget
 *
 * @class
 *
 * @constructor
 * @param {jQuery.Deferred} info Promise that resolves to an info object.
 * @param {Object} [config] Configuration options
 */
var ipInfoInfoboxWidget = function ( info, config ) {
	// Parent constructor
	ipInfoInfoboxWidget.super.call( this, info, config );
};

/* Setup */

OO.inheritClass( ipInfoInfoboxWidget, ipInfoWidget );

/**
 * Build HTML to display the IP information.
 *
 * @param {Object} info Data returned by the API
 * @return {Object}
 */
ipInfoInfoboxWidget.prototype.buildMarkup = function ( info ) {
	var $edits;
	var activeBlocks;

	var location = ( info.data[ 'ipinfo-source-geoip2' ].location || [] )
		.concat( info.data[ 'ipinfo-source-geoip2' ].country || [] )
		.map( function ( item ) {
			return item.label;
		} ).join( mw.msg( 'comma-separator' ) );
	location = location.length ? location : null;

	// Check to see if we have the appropriate data before trying to translate values
	if ( info.data[ 'ipinfo-source-block' ].numActiveBlocks !== undefined ) {
		activeBlocks = mw.msg( 'ipinfo-value-active-blocks', info.data[ 'ipinfo-source-block' ].numActiveBlocks );
	}

	if ( info.data[ 'ipinfo-source-contributions' ].numLocalEdits !== undefined || info.data[ 'ipinfo-source-contributions' ].numRecentEdits !== undefined ) {
		var localEdits = mw.msg( 'ipinfo-value-local-edits', info.data[ 'ipinfo-source-contributions' ].numLocalEdits );

		var $recentEdits = $( '<span>' ).addClass( 'ext-ipinfo-widget-value-recent-edits' )
			.append( mw.msg( 'ipinfo-value-recent-edits', info.data[ 'ipinfo-source-contributions' ].numRecentEdits ) );

		$edits = $( '<span>' ).append(
			localEdits,
			$( '<br>' ),
			$recentEdits
		);
	}

	var proxyTypes, $proxyTypes;
	if ( info.data[ 'ipinfo-source-geoip2' ].proxyType ) {
		// Filter for true values of proxyType
		proxyTypes = Object.keys( info.data[ 'ipinfo-source-geoip2' ].proxyType )
			.filter( function ( proxyTypeKey ) {
				return info.data[ 'ipinfo-source-geoip2' ].proxyType[ proxyTypeKey ];
			} );

		// If there are any known proxy types, transform the array into a list of values
		if ( proxyTypes.length ) {
			$proxyTypes = $( '<ul>' );
			proxyTypes.forEach( function ( proxyType ) {
				// * ipinfo-property-value-proxytype-isanonymousvpn
				// * ipinfo-property-value-proxytype-ispublicproxy
				// * ipinfo-property-value-proxytype-isresidentialproxy
				// * ipinfo-property-value-proxytype-islegitimateproxy
				// * ipinfo-property-value-proxytype-istorexitnode
				// * ipinfo-property-value-proxytype-ishostingprovider
				$proxyTypes.append( $( '<li>' ).text( mw.msg( 'ipinfo-property-value-proxytype-' + proxyType.toLowerCase() ) ) );
			} );
		}
	}

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
					mw.msg( 'ipinfo-property-tooltip-usertype' ) ),
				this.generatePropertyMarkup(
					$proxyTypes,
					mw.msg( 'ipinfo-property-label-proxytypes' ),
					mw.msg( 'ipinfo-property-tooltip-proxytypes' )
				)
			)
		).append(
			$( '<div>' ).addClass( 'ext-ipinfo-widget-properties-col' ).append(
				this.generatePropertyMarkup( activeBlocks, mw.msg( 'ipinfo-property-label-active-blocks' ) ),
				this.generatePropertyMarkup( $edits, mw.msg( 'ipinfo-property-label-edits' ) ),
				$( '<div>' ).addClass( 'ext-ipinfo-widget-property-source' ).text( mw.msg( 'ipinfo-source-geoip2' ) )
			)
		);
};

module.exports = ipInfoInfoboxWidget;
