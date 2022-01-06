( function () {
	mw.IpInfo = mw.IpInfo || {};

	/**
	 * IP Info Widget
	 *
	 * @class
	 *
	 * @constructor
	 * @param {jQuery.Deferred} info Promise that resolves to an info object.
	 * @param {Object} [config] Configuration options
	 */
	mw.IpInfo.IpInfoWidget = function ( info, config ) {
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
	 * Build HTML to display the IP information.
	 *
	 * @method
	 * @abstract
	 * @param {Object} info Data returned by the API
	 * @return {Object}
	 */
	mw.IpInfo.IpInfoWidget.prototype.buildMarkup = null;

	/**
	 * Success callback for the info promise.
	 *
	 * @param {Object} info
	 */
	mw.IpInfo.IpInfoWidget.prototype.success = function ( info ) {
		if ( !info ) {
			// The IP address did not match the log or revision ID
			this.displayError( mw.msg( 'ipinfo-widget-error-wrong-ip' ) );
			return;
		}

		this.$element.append( this.buildMarkup( info ) );
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
	 * Generate HTML for a property. All properties are shown regardless if a value exists or not.
	 *
	 * @param {Object} propertyValue
	 * @param {string} propertyLabel
	 * @param {string} propertyTooltip
	 * @return {Object}
	 */
	mw.IpInfo.IpInfoWidget.prototype.generatePropertyMarkup = function (
		propertyValue,
		propertyLabel,
		propertyTooltip
	) {
		var $propertyContent = $( '<div>' ).addClass( 'ext-ipinfo-widget-property' ).attr( 'data-property', propertyLabel );
		var $propertyLabel = $( '<dt>' ).addClass( 'ext-ipinfo-widget-property-label' ).text( propertyLabel );
		if ( propertyTooltip ) {
			var $propertyTooltip = new OO.ui.PopupButtonWidget( {
				icon: 'info',
				framed: false,
				popup: {
					$content: $( '<span>' ).text( propertyTooltip ),
					padded: true,
					align: 'backwards'
				},
				classes: [ 'ext-ipinfo-widget-property-tooltip' ]
			} );
			$propertyLabel.append( $propertyTooltip.$element );
		}
		$propertyContent.append(
			$propertyLabel
		);
		if ( propertyValue || propertyValue === 0 ) {
			$propertyContent.append(
				$( '<dd>' ).addClass( 'ext-ipinfo-widget-property-value' ).append( propertyValue )
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
