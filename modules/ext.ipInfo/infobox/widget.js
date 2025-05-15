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
	var blockListUrl, $blockListLink, deletedEditsUrl, $deletedEditsLink;
	var localizedCountryName = this.getLocalizedCountryName(
		info.data[ 'ipinfo-source-geoip2' ].countryNames,
		info[ 'language-fallback' ]
	);

	var location = this.getLocation(
		info.data[ 'ipinfo-source-geoip2' ].location,
		localizedCountryName
	);

	var activeBlocks = this.getActiveBlocks( info.data[ 'ipinfo-source-block' ].numActiveBlocks );
	var blockLogUrl = mw.util.getUrl( 'Special:Log' ) + '?type=block&page=' + info.subject;
	var $blockLogLink = $( '<div>' )
		.addClass( 'ext-ipinfo-block-links' )
		.append( $( '<a>' )
			.attr( 'href', blockLogUrl )
			.text( mw.msg( 'ipinfo-active-blocks-url-text' ) ) );

	if ( info.data[ 'ipinfo-source-block' ].numActiveBlocks ) {
		blockListUrl = mw.util.getUrl( 'Special:BlockList' ) + '?wpTarget=' + info.subject;
		$blockListLink = $( '<div>' )
			.addClass( 'ext-ipinfo-block-links' )
			.append( $( '<a>' )
				.attr( 'href', blockListUrl )
				.text( mw.msg( 'ipinfo-blocklist-url-text' ) ) );
	}

	var $edits = this.getEdits(
		info.data[ 'ipinfo-source-contributions' ].numLocalEdits,
		info.data[ 'ipinfo-source-contributions' ].numRecentEdits,
		info.data[ 'ipinfo-source-contributions' ].numDeletedEdits
	);

	if ( info.data[ 'ipinfo-source-contributions' ].numDeletedEdits ) {
		deletedEditsUrl = mw.util.getUrl( 'Special:DeletedContributions' ) + '?target=' + info.subject;
		$deletedEditsLink = $( '<div>' )
			.addClass( 'ext-ipinfo-contribution-links' )
			.append( $( '<a>' )
				.attr( 'href', deletedEditsUrl )
				.text( mw.msg( 'ipinfo-deleted-edits-url-text' ) ) );
	}

	// IPoid-provided data
	var behaviors = info.data[ 'ipinfo-source-ipoid' ].behaviors;
	if ( behaviors ) {
		if ( behaviors.length ) {
			behaviors = behaviors.join( '</br>' );
		} else {
			behaviors = null;
		}
	}
	var risks = info.data[ 'ipinfo-source-ipoid' ].risks;
	risks = risks ? this.getRisks( info.data[ 'ipinfo-source-ipoid' ].risks ) : risks;
	risks = risks ? risks.join( '<br />' ) : risks;
	var connectionTypes = info.data[ 'ipinfo-source-ipoid' ].connectionTypes;
	connectionTypes = connectionTypes ? this.getConnectionTypes( info.data[ 'ipinfo-source-ipoid' ].connectionTypes ) : connectionTypes;
	connectionTypes = connectionTypes ? connectionTypes.join( '<br />' ) : connectionTypes;
	var tunnelOperators = info.data[ 'ipinfo-source-ipoid' ].tunnelOperators;
	if ( tunnelOperators ) {
		if ( tunnelOperators.length ) {
			tunnelOperators = tunnelOperators.join( '</br>' );
		} else {
			tunnelOperators = null;
		}
	}
	var proxies = info.data[ 'ipinfo-source-ipoid' ].proxies;
	if ( proxies ) {
		if ( proxies.length ) {
			proxies = proxies.join( '</br>' );
		} else {
			proxies = null;
		}
	}

	var ipversion = mw.util.isIPv4Address( info.subject, true ) ?
		mw.message( 'ipinfo-value-ipversion-ipv4' ).escaped() :
		mw.message( 'ipinfo-value-ipversion-ipv6' ).escaped();

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
					'behaviors',
					behaviors,
					mw.msg( 'ipinfo-property-label-behaviors' ),
					mw.msg( 'ipinfo-property-tooltip-behaviors' ) ),
				this.generatePropertyMarkup(
					'risks',
					risks,
					mw.msg( 'ipinfo-property-label-risks' ),
					mw.msg( 'ipinfo-property-tooltip-risks' ) ),
				this.generatePropertyMarkup(
					'connectionTypes',
					connectionTypes,
					mw.msg( 'ipinfo-property-label-connectiontypes' ),
					mw.msg( 'ipinfo-property-tooltip-connectiontypes' ) ),
				this.generatePropertyMarkup(
					'tunnelOperators',
					tunnelOperators,
					mw.msg( 'ipinfo-property-label-tunneloperators' ),
					mw.msg( 'ipinfo-property-tooltip-tunneloperators' ) ),
				this.generatePropertyMarkup(
					'proxies',
					proxies,
					mw.msg( 'ipinfo-property-label-proxies' ),
					mw.msg( 'ipinfo-property-tooltip-proxies' ) ),
				this.generatePropertyMarkup(
					'userCount',
					info.data[ 'ipinfo-source-ipoid' ].numUsersOnThisIP,
					mw.msg( 'ipinfo-property-label-usercount' ),
					mw.msg( 'ipinfo-property-tooltip-usercount' )
				)
			)
		).append(
			$( '<div>' ).addClass( 'ext-ipinfo-widget-properties-col' ).append(
				this.generatePropertyMarkup(
					'active-blocks',
					activeBlocks,
					mw.msg( 'ipinfo-property-label-active-blocks' ) ).append( $blockLogLink, $blockListLink ),
				this.generatePropertyMarkup(
					'edits',
					$edits,
					mw.msg( 'ipinfo-property-label-edits' ) ).append( $deletedEditsLink ),
				$( '<div>' ).addClass( 'ext-ipinfo-widget-property-source' ).html(
					mw.message( 'ipinfo-source-geoip2' ).parse()
				)
			)
		);

	mw.hook( 'ext.ipinfo.infobox.widget' ).fire( $info );

	return $info;
};

module.exports = ipInfoInfoboxWidget;
