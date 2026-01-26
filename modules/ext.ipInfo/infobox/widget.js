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
	let behaviors, risks, connectionTypes, tunnelOperators, proxies, numUsersOnThisIP;

	const ipoidData = info.data && info.data[ 'ipinfo-source-ipoid' ];
	const hasIpoidData = ipoidData ?
		Object.keys( ipoidData ).some( ( k ) => ipoidData[ k ] !== null ) :
		false;

	if ( hasIpoidData ) {
		behaviors = ipoidData.behaviors && ipoidData.behaviors.length ?
			ipoidData.behaviors.join( '</br>' ) :
			null;

		risks = ipoidData.risks ? this.getRisks( ipoidData.risks ) : null;
		risks = risks ? risks.join( '<br />' ) : risks;

		connectionTypes = ipoidData.connectionTypes ?
			this.getConnectionTypes( ipoidData.connectionTypes ) :
			null;
		connectionTypes = connectionTypes ? connectionTypes.join( '<br />' ) : connectionTypes;

		tunnelOperators = ipoidData.tunnelOperators && ipoidData.tunnelOperators.length ?
			ipoidData.tunnelOperators.join( '</br>' ) :
			null;

		proxies = ipoidData.proxies && ipoidData.proxies.length ?
			ipoidData.proxies.join( '</br>' ) :
			null;

		numUsersOnThisIP = ipoidData.numUsersOnThisIP;
	}

	const ipversion = info.data[ 'ipinfo-source-ipversion' ].version;
	// Possible message keys used here:
	// * ipinfo-value-ipversion-ipv4
	// * ipinfo-value-ipversion-ipv6
	const ipVersionText = ipversion ? mw.message( `ipinfo-value-ipversion-${ ipversion }` ).escaped() : '';

	let $numIPAddresses = $( '' );
	if ( info.data[ 'ipinfo-source-ip-count' ].numIPAddresses ) {
		$numIPAddresses = this.generatePropertyMarkup(
			'num-ip-addresses',
			info.data[ 'ipinfo-source-ip-count' ].numIPAddresses,
			mw.msg( 'ipinfo-property-label-number-of-ips' )
		);
	}

	const $info = $( '<dl>' ).addClass( 'ext-ipinfo-widget-properties' )
		.append(
			$( '<div>' ).addClass( 'ext-ipinfo-widget-properties-col' ).append(
				this.generatePropertyMarkup( 'location', location, mw.msg( 'ipinfo-property-label-location' ) ),
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
					numUsersOnThisIP,
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

	mw.hook( 'ext.ipinfo.infobox.widget' ).fire( $info, info, this.generatePropertyMarkup );

	return $info;
};

/**
 * Success callback for the info promise.
 *
 * @param {Object} info
 */
ipInfoInfoboxWidget.prototype.success = function ( info ) {
	if ( !info ) {
		return;
	}

	const ipCount = info.data[ 'ipinfo-source-ip-count' ].numIPAddresses;
	if ( ipCount && ipCount > 1 && mw.util.isTemporaryUser( info.subject ) ) {
		const ipCountMsg = new OO.ui.MessageWidget( {
			type: 'notice',
			label: new OO.ui.HtmlSnippet( mw.message( 'ipinfo-infobox-temporary-account-help', info.subject ).parse() )
		} );

		this.$element.append( ipCountMsg.$element );
	}

	ipInfoInfoboxWidget.super.prototype.success.call( this, info );
};

module.exports = ipInfoInfoboxWidget;
