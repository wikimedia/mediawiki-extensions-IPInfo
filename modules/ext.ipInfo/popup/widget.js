var ipInfoWidget = require( '../widget.js' );

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

	var $info, $linkOutURL, $linkOut;
	$info = $( '<dl>' ).addClass( 'ext-ipinfo-widget-property-properties' ).append(
		this.generatePropertyMarkup( location, mw.msg( 'ipinfo-property-label-location' ) ),
		this.generatePropertyMarkup( info.data[ 'ipinfo-source-geoip2' ].organization, mw.msg( 'ipinfo-property-label-organization' ) ),
		this.generatePropertyMarkup( activeBlocks, mw.msg( 'ipinfo-property-label-active-blocks' ) ),
		this.generatePropertyMarkup( $edits, mw.msg( 'ipinfo-property-label-edits' ) )
	);

	// Popup links out to the Special:Contributions page of the ip
	$linkOutURL = mw.util.getUrl( 'Special:Contributions' ) + '/' + info.subject + '?openInfobox=true';
	$linkOut = $( '<a>' )
		.addClass( 'ext-ipinfo-widget-popup-linkout' )
		.attr( 'href', $linkOutURL )
		.text( 'Special:Contributions/' + info.subject );

	return $( '<div>' )
		.append( $linkOut )
		.append( $info );
};

module.exports = ipInfoPopupWidget;
