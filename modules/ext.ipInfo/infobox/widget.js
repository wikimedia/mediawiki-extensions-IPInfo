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
	var location = this.getLocation(
		info.data[ 'ipinfo-source-geoip2' ].location,
		info.data[ 'ipinfo-source-geoip2' ].country
	);

	var activeBlocks = this.getActiveBlocks( info.data[ 'ipinfo-source-block' ].numActiveBlocks );
	var blockLogUrl, $blockLogLink, blockListUrl, $blockListLink;
	blockLogUrl = mw.util.getUrl( 'Special:Log' ) + '?type=block&page=' + info.subject;
	blockListUrl = mw.util.getUrl( 'Special:BlockList' ) + '?wpTarget=' + info.subject;
	$blockLogLink = $( '<div>' )
		.addClass( 'ext-ipinfo-block-links' )
		.append( $( '<a>' )
			.attr( 'href', blockLogUrl )
			.text( mw.msg( 'ipinfo-active-blocks-url-text' ) ) );

	if ( info.data[ 'ipinfo-source-block' ].numActiveBlocks ) {
		$blockListLink = $( '<div>' )
			.addClass( 'ext-ipinfo-block-links' )
			.append( $( '<a>' )
				.attr( 'href', blockListUrl )
				.text( mw.msg( 'ipinfo-blocklist-url-text' ) ) );
	}

	var $edits = this.getEdits(
		info.data[ 'ipinfo-source-contributions' ].numLocalEdits,
		info.data[ 'ipinfo-source-contributions' ].numRecentEdits
	);

	var $proxyTypes = this.getProxyTypes( info.data[ 'ipinfo-source-geoip2' ].proxyType );
	var connectionTypes = this.getConnectionTypes( info.data[ 'ipinfo-source-geoip2' ].connectionType );
	var userType = this.getUserTypes( info.data[ 'ipinfo-source-geoip2' ].userType );

	var ipversion = mw.util.isIPv4Address( info.subject, true ) ?
		mw.msg( 'ipinfo-value-ipversion-ipv4' ) :
		mw.msg( 'ipinfo-value-ipversion-ipv6' );

	var $info = $( '<dl>' ).addClass( 'ext-ipinfo-widget-properties' )
		.append(
			$( '<div>' ).addClass( 'ext-ipinfo-widget-properties-col' ).append(
				this.generatePropertyMarkup( 'location', location, mw.msg( 'ipinfo-property-label-location' ) ),
				this.generatePropertyMarkup( 'isp', info.data[ 'ipinfo-source-geoip2' ].isp, mw.msg( 'ipinfo-property-label-isp' ) ),
				this.generatePropertyMarkup( 'asn',
					info.data[ 'ipinfo-source-geoip2' ].asn,
					mw.msg( 'ipinfo-property-label-asn' ),
					mw.msg( 'ipinfo-property-tooltip-asn' ) ),
				this.generatePropertyMarkup( 'organization', info.data[ 'ipinfo-source-geoip2' ].organization, mw.msg( 'ipinfo-property-label-organization' ) ),
				this.generatePropertyMarkup( 'version', ipversion, mw.msg( 'ipinfo-property-label-ipversion' ) )
			)
		).append(
			$( '<div>' ).addClass( 'ext-ipinfo-widget-properties-col' ).append(
				this.generatePropertyMarkup(
					'connectiontype',
					connectionTypes,
					mw.msg( 'ipinfo-property-label-connectiontype' ),
					mw.msg( 'ipinfo-property-tooltip-connectiontype' ) ),
				this.generatePropertyMarkup(
					'usertype',
					userType,
					mw.msg( 'ipinfo-property-label-usertype' ),
					mw.msg( 'ipinfo-property-tooltip-usertype' ) ),
				this.generatePropertyMarkup(
					'proxytypes',
					$proxyTypes,
					mw.msg( 'ipinfo-property-label-proxytypes' ),
					mw.msg( 'ipinfo-property-tooltip-proxytypes' )
				)
			)
		).append(
			$( '<div>' ).addClass( 'ext-ipinfo-widget-properties-col' ).append(
				this.generatePropertyMarkup(
					'active-blocks',
					activeBlocks,
					mw.msg( 'ipinfo-property-label-active-blocks' ) ).append( $blockLogLink, $blockListLink ),
				this.generatePropertyMarkup( 'edits', $edits, mw.msg( 'ipinfo-property-label-edits' ) ),
				$( '<div>' ).addClass( 'ext-ipinfo-widget-property-source' ).html(
					mw.message( 'ipinfo-source-geoip2' ).parse()
				)
			)
		);

	mw.hook( 'ext.ipinfo.infobox.widget' ).fire( $info );

	return $info;
};

module.exports = ipInfoInfoboxWidget;
