const ipInfoWidget = require( '../widget.js' );

/**
 * Infobox Widget
 *
 * @class
 *
 * @constructor
 * @param {jQuery.Deferred} info Promise that resolves to an info object.
 * @param {Object} [config] Configuration options
 */
const ipInfoInfoboxWidget = function ( info, config ) {
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
	let blockListUrl, $blockListLink, deletedEditsUrl, $deletedEditsLink;
	const localizedCountryName = this.getLocalizedCountryName(
		info.data[ 'ipinfo-source-geoip2' ].countryNames,
		info[ 'language-fallback' ]
	);

	const location = this.getLocation(
		info.data[ 'ipinfo-source-geoip2' ].location,
		localizedCountryName
	);

	const activeBlocks = this.getActiveBlocks( info.data[ 'ipinfo-source-block' ].numActiveBlocks );
	const blockLogUrl = mw.util.getUrl( 'Special:Log' ) + '?type=block&page=' + info.subject;
	const $blockLogLink = $( '<div>' )
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

	const $edits = this.getEdits(
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
	let behaviors = info.data[ 'ipinfo-source-ipoid' ].behaviors;
	if ( behaviors ) {
		if ( behaviors.length ) {
			behaviors = behaviors.join( '</br>' );
		} else {
			behaviors = null;
		}
	}
	let risks = info.data[ 'ipinfo-source-ipoid' ].risks;
	risks = risks ? this.getRisks( info.data[ 'ipinfo-source-ipoid' ].risks ) : risks;
	risks = risks ? risks.join( '<br />' ) : risks;
	let connectionTypes = info.data[ 'ipinfo-source-ipoid' ].connectionTypes;
	connectionTypes = connectionTypes ? this.getConnectionTypes( info.data[ 'ipinfo-source-ipoid' ].connectionTypes ) : connectionTypes;
	connectionTypes = connectionTypes ? connectionTypes.join( '<br />' ) : connectionTypes;
	let tunnelOperators = info.data[ 'ipinfo-source-ipoid' ].tunnelOperators;
	if ( tunnelOperators ) {
		if ( tunnelOperators.length ) {
			tunnelOperators = tunnelOperators.join( '</br>' );
		} else {
			tunnelOperators = null;
		}
	}
	let proxies = info.data[ 'ipinfo-source-ipoid' ].proxies;
	if ( proxies ) {
		if ( proxies.length ) {
			proxies = proxies.join( '</br>' );
		} else {
			proxies = null;
		}
	}

	const ipversion = info.data[ 'ipinfo-source-ipversion' ].version;
	// Possible message keys used here:
	// * ipinfo-value-ipversion-ipv4
	// * ipinfo-value-ipversion-ipv6
	const ipVersionText = ipversion ? mw.msg( `ipinfo-value-ipversion-${ ipversion }` ) : '';

	let $numIPAddresses = $( '' );
	if ( info.data[ 'ipinfo-source-ip-count' ].numIPAddresses ) {
		$numIPAddresses = this.generatePropertyMarkup(
			'num-ip-addresses',
			info.data[ 'ipinfo-source-ip-count' ].numIPAddresses,
			mw.msg( 'ipinfo-property-label-number-of-ips' )
		);
	}

	let $specialIpInfoLink = $( '' );
	if ( mw.util.isTemporaryUser( info.subject ) ) {
		$specialIpInfoLink = $( '<div>' )
			.addClass( 'ext-ipinfo-widget-property' )
			.html( mw.message( 'ipinfo-widget-special-ipinfo-link', info.subject ).parse() );
	}

	const $info = $( '<dl>' ).addClass( 'ext-ipinfo-widget-properties' )
		.append(
			$( '<div>' ).addClass( 'ext-ipinfo-widget-properties-col' ).append(
				this.generatePropertyMarkup( 'location', location, mw.msg( 'ipinfo-property-label-location' ) ),
				this.generatePropertyMarkup( 'isp', info.data[ 'ipinfo-source-geoip2' ].isp, mw.msg( 'ipinfo-property-label-isp' ) ),
				this.generatePropertyMarkup( 'asn',
					info.data[ 'ipinfo-source-geoip2' ].asn,
					mw.msg( 'ipinfo-property-label-asn' ),
					mw.msg( 'ipinfo-property-tooltip-asn' ) ),
				this.generatePropertyMarkup( 'organization', info.data[ 'ipinfo-source-geoip2' ].organization, mw.msg( 'ipinfo-property-label-organization' ) ),
				this.generatePropertyMarkup( 'version', ipVersionText, mw.msg( 'ipinfo-property-label-ipversion' ) )
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
				$numIPAddresses,
				this.generatePropertyMarkup(
					'active-blocks',
					activeBlocks,
					mw.msg( 'ipinfo-property-label-active-blocks' ) ).append( $blockLogLink, $blockListLink ),
				this.generatePropertyMarkup(
					'edits',
					$edits,
					mw.msg( 'ipinfo-property-label-edits' ) ).append( $deletedEditsLink ),
				$specialIpInfoLink,
				$( '<div>' )
					.addClass( 'ext-ipinfo-widget-property-source' )
					.append(
						$( '<p>' )
							.addClass( 'ext-ipinfo-widget-property-source__help' )
							.html( mw.message( 'ipinfo-help-text' ).parse() )
					)
					.append( $( '<p>' ).html( mw.message( 'ipinfo-learn-more-link' ).parse() ) )
			)
		);

	mw.hook( 'ext.ipinfo.infobox.widget' ).fire( $info );

	return $info;
};

module.exports = ipInfoInfoboxWidget;
