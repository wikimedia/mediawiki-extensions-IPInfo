var ipInfoWidget = require( '../widget.js' );
var eventLogger = require( '../log.js' );

/**
 * Popup Widget
 *
 * @class
 *
 * @constructor
 * @param {jQuery.Deferred} info Promise that resolves to an info object.
 * @param {Object} [config] Configuration options
 */
var ipInfoPopupWidget = function ( info, config ) {
	// Parent constructor
	ipInfoPopupWidget.super.call( this, info, config );
};

/* Setup */

OO.inheritClass( ipInfoPopupWidget, ipInfoWidget );

/**
 * Build HTML to display the IP information.
 *
 * @param {Object} info Data returned by the API
 * @return {Object}
 */
ipInfoPopupWidget.prototype.buildMarkup = function ( info ) {
	var location = this.getLocation(
		info.data[ 'ipinfo-source-geoip2' ].location,
		info.data[ 'ipinfo-source-geoip2' ].country
	);

	var activeBlocks = this.getActiveBlocks( info.data[ 'ipinfo-source-block' ].numActiveBlocks );

	var $edits = this.getEdits(
		info.data[ 'ipinfo-source-contributions' ].numLocalEdits,
		info.data[ 'ipinfo-source-contributions' ].numRecentEdits
	);

	var $info, $linkOutURL, $linkOut;
	$info = $( '<dl>' ).addClass( 'ext-ipinfo-widget-property-properties' ).append(
		this.generatePropertyMarkup( 'location', location, mw.msg( 'ipinfo-property-label-location' ) ),
		this.generatePropertyMarkup( 'organization', info.data[ 'ipinfo-source-geoip2' ].organization, mw.msg( 'ipinfo-property-label-organization' ) ),
		this.generatePropertyMarkup( 'active-blocks', activeBlocks, mw.msg( 'ipinfo-property-label-active-blocks' ) ),
		this.generatePropertyMarkup( 'edits', $edits, mw.msg( 'ipinfo-property-label-edits' ) )
	);

	// Popup links out to the Special:Contributions page of the ip
	$linkOutURL = mw.util.getUrl( 'Special:Contributions' ) + '/' + info.subject + '?openInfobox=true';
	$linkOut = $( '<a>' )
		.addClass( 'ext-ipinfo-widget-popup-linkout' )
		.attr( 'href', $linkOutURL )
		.text( 'Special:Contributions/' + info.subject )
		.on( 'click', function () {
			eventLogger.log( 'open_infobox', 'popup' );
		} );

	return $( '<div>' )
		.append( $linkOut )
		.append( $info );
};

module.exports = ipInfoPopupWidget;
