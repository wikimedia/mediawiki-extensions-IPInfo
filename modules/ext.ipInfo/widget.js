( function () {
	mw.IpInfo = mw.IpInfo || {};

	/**
	 * IP Info Widget
	 *
	 * @class
	 *
	 * @constructor
	 * @param {jQuery.Deferred} info Promise that resolves to an info object.
	 * @param {Object} [display] List of properties that should be shown in widget
	 * @param {Object} [config] Configuration options
	 */
	mw.IpInfo.IpInfoWidget = function ( info, display, config ) {
		// Config initialization
		config = $.extend( {
			classes: [
				'ext-ipinfo-widget'
			]
		}, config );

		// Parent constructor
		mw.IpInfo.IpInfoWidget.super.call( this, config );

		// Mixin constructors
		OO.ui.mixin.PendingElement.call( this, config );

		// Set pending element.
		this.pushPending();

		// Pass along display
		this.display = display;

		// Promise handler
		info.then(
			this.success.bind( this ),
			this.failure.bind( this )
		).always( this.always.bind( this ) );
	};

	/* Setup */

	OO.inheritClass( mw.IpInfo.IpInfoWidget, OO.ui.Widget );
	OO.mixinClass( mw.IpInfo.IpInfoWidget, OO.ui.mixin.PendingElement );

	/**
	 * Success callback for the info promise.
	 *
	 * @param {Object} info
	 */
	mw.IpInfo.IpInfoWidget.prototype.success = function ( info ) {
		var widget = this,
			display = this.display,
			$content;

		if ( info ) {
			$content = $( '<dl>' ).addClass( 'ext-ipinfo-widget-property-properties' );
			info.data.forEach( function ( datum ) {
				display.forEach( function ( property ) {
					var formattedData = widget.transformData( datum, property );

					// All properties are shown regardless if a value exists or not
					$content.append( widget.generateMarkup( formattedData, property ) );
				} );

				// Add source disclaimer
				if ( datum.source ) {
					// The following messages can be passed here:
					// * ipinfo-source-geoip2
					// * ipinfo-source-<sourcename>
					$content.append( $( '<div>' ).addClass( 'ext-ipinfo-widget-property-source' ).text( mw.msg( datum.source ) ) );
				}
				widget.$element.append( $content );
			} );
		} else {
			// The IP address did not match the log or revision ID
			this.displayError( mw.msg( 'ipinfo-widget-error-wrong-ip' ) );
		}
	};

	/**
	 * Failure callback for the info promise.
	 *
	 * @param {Object} error
	 */
	mw.IpInfo.IpInfoWidget.prototype.failure = function ( error ) {
		if ( error.responseJSON && error.responseJSON.messageTranslations ) {
			this.displayError( error.responseJSON.messageTranslations[
				mw.config.get( 'wgContentLanguage' )
			] );
		} else {
			this.displayError();
		}
	};

	/**
	 * Display an error, given an error message.
	 *
	 * @param {string} [label] Error message
	 */
	mw.IpInfo.IpInfoWidget.prototype.displayError = function ( label ) {
		this.$element.append(
			new OO.ui.MessageWidget( {
				type: 'error',
				label: label || mw.msg( 'ipinfo-widget-error-default' ),
				inline: true,
				classes: [ 'ext-ipinfo-widget-error' ]
			} ).$element
		);
	};

	/**
	 * Callback to call under success or failure.
	 *
	 * @param {Object} info
	 */
	mw.IpInfo.IpInfoWidget.prototype.always = function () {
		this.popPending();
	};

	/**
	 * Take data returned from the API and
	 * transform it into its client-side representation
	 *
	 * @param {Object} sourceData
	 * @param {string} property
	 * @return {string|null}
	 */
	mw.IpInfo.IpInfoWidget.prototype.transformData = function ( sourceData, property ) {
		switch ( property ) {
			case 'asn':
				return sourceData.asn;
			case 'organization':
				return sourceData.organization;
			case 'location':
				return sourceData.location.map( function ( item ) {
					return item.label;
				} ).join( mw.msg( 'comma-separator' ) );
			case 'isp':
				return sourceData.isp;
			default:
				return null;
		}
	};

	/**
	 * Take transformed data and wrap it in markup
	 *
	 * @param {Object} formattedData
	 * @param {string} property
	 * @return {Object}
	 */
	mw.IpInfo.IpInfoWidget.prototype.generateMarkup = function ( formattedData, property ) {
		var $propertyContent = $( '<div>' ).addClass( 'ext-ipinfo-widget-property' ).attr( 'data-property', property );
		// Messages that can be used here:
		// * ipinfo-property-label-location
		// * ipinfo-property-label-isp
		// * ipinfo-property-label-asn
		// * ipinfo-property-label-source
		// * ipinfo-property-label-organization
		$propertyContent.append(
			$( '<dt>' ).addClass( 'ext-ipinfo-widget-property-label' ).text( mw.msg( 'ipinfo-property-label-' + property ) )
		);
		if ( formattedData ) {
			$propertyContent.append(
				$( '<dd>' ).addClass( 'ext-ipinfo-widget-property-value' ).append( formattedData )
			);
		} else {
			$propertyContent.addClass( 'ext-ipinfo-widget-property-no-data' );
			$propertyContent.append(
				$( '<dd>' ).addClass( 'ext-ipinfo-widget-property-value' ).text( mw.msg( 'ipinfo-property-no-data' ) )
			);
		}
		return $propertyContent;
	};
}() );
