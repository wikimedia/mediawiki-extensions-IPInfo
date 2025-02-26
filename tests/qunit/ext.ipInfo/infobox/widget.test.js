'use strict';

const IpInfoInfoboxWidget = require( '../../../../modules/ext.ipInfo/infobox/widget.js' );
const { waitUntilElementAppears } = require( '../../utils.js' );

QUnit.module( 'ext.ipInfo.infobox.widget', QUnit.newMwEnvironment() );

function setUpDocumentForTest( ipPanelWidget ) {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );

	// Create a FieldsetLayout to place the infobox widget into
	const node = document.createElement( 'div' );
	// Add the target input to the DOM. The data-ooui attribute value
	// is hardcoded as there is no way to get it in a QUnit test context.
	const $fieldsetLayout = new OO.ui.FieldsetLayout( {
		items: [],
		classes: [ 'ext-ipinfo-collapsible-layout' ]
	} ).$element;
	$fieldsetLayout.attr(
		'data-ooui',
		'{"_":"MediaWiki\\HTMLForm\\CollapsibleFieldsetLayout","$overlay":true,"label":{},' +
			'"items":[],"classes":["ext-ipinfo-collapsible-layout"]}'
	);
	// Add the ipPanelWidget to the fieldset
	$fieldsetLayout.find( '.oo-ui-fieldsetLayout-group' ).append( ipPanelWidget.$element );
	// Add the HTML structure to the QUnit fixture element.
	node.appendChild( $fieldsetLayout[ 0 ] );
	$qunitFixture.html( node );
}

QUnit.test( 'Displays error correctly when request fails with translated message', ( assert ) => {
	mw.config.set( 'wgContentLanguage', 'en' );

	// Set up the IPInfo infobox in the widget
	const deferred = $.Deferred();
	const ipPanelWidget = new IpInfoInfoboxWidget( deferred.promise() );
	setUpDocumentForTest( ipPanelWidget );

	// Reject the deferred promise, so an error is displayed. Use a translated message to test that behaviour
	// in ext.ipInfo/widget.js. Error object structure is simulated to be the structure returned in
	// ext.ipInfo/rest.js.
	deferred.reject( { xhr: { responseJSON: { messageTranslations: {
		en: 'Testing error message translation.'
	} } } } );

	// Check that the error widget is present in the IPInfoInfoboxWidget and that it displays our
	// custom messsage returned by the fake request.
	const done = assert.async();
	waitUntilElementAppears( '.ext-ipinfo-widget-error' ).then( () => {
		const $ipInfoWidgetError = $( '.ext-ipinfo-widget-error', ipPanelWidget.$element );
		assert.strictEqual( $ipInfoWidgetError.length, 1, 'IPInfo error widget exists' );
		assert.strictEqual(
			$ipInfoWidgetError.text(),
			'Testing error message translation.',
			'IPInfo error widget contains correct text'
		);

		done();
	} );
} );

QUnit.test( 'Displays error correctly when request fails without translated message', ( assert ) => {
	mw.config.set( 'wgContentLanguage', 'en' );

	// Set up the IPInfo infobox in the widget
	const deferred = $.Deferred();
	const ipPanelWidget = new IpInfoInfoboxWidget( deferred.promise() );
	setUpDocumentForTest( ipPanelWidget );

	// Reject the deferred promise, so an error is displayed.
	deferred.reject( { xhr: { responseJSON: { test: 'test' } } } );

	// Check that the error widget is present in the IPInfoInfoboxWidget and that it displays our
	// custom messsage returned by the fake request.
	const done = assert.async();
	waitUntilElementAppears( '.ext-ipinfo-widget-error' ).then( () => {
		const $ipInfoWidgetError = $( '.ext-ipinfo-widget-error', ipPanelWidget.$element );
		assert.strictEqual( $ipInfoWidgetError.length, 1, 'IPInfo error widget exists' );
		assert.strictEqual(
			$ipInfoWidgetError.text(),
			'(ipinfo-widget-error-default)',
			'IPInfo error widget contains correct text'
		);

		done();
	} );
} );
