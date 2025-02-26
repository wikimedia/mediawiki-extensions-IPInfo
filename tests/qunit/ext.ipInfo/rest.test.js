'use strict';

/**
 * Modified copy of the rest.test.js file from mediawiki/extensions/CheckUser
 */

const postToRestApi = require( '../../../modules/ext.ipInfo/rest.js' );

let server;

QUnit.module( 'ext.ipInfo.rest', QUnit.newMwEnvironment( {
	beforeEach: function () {
		mw.config.set( 'wgUserLanguage', 'qqx' );
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
		server = this.server;
	},
	afterEach: function () {
		server.restore();
	}
} ) );

function commonPerformTest(
	assert, endpoint, id, dataContext, expectedUrl, responseCode, responseContent, shouldFail
) {
	const done = assert.async();
	server.respond( ( request ) => {
		if ( request.url.endsWith( expectedUrl ) ) {
			assert.strictEqual( request.method, 'POST' );
			request.respond(
				responseCode,
				{ 'Content-Type': 'application/json' },
				JSON.stringify( responseContent )
			);
		} else if ( request.url.includes( 'type=csrf' ) && request.url.includes( 'meta=tokens' ) ) {
			// Handle the request for a new CSRF token by returning a fake token.
			request.respond( 200, { 'Content-Type': 'application/json' }, JSON.stringify( {
				query: { tokens: { csrftoken: 'newtoken' } }
			} ) );
		} else {
			// All API requests except the above are not expected to be called during the test.
			// To prevent the test from silently failing, we will fail the test if an
			// unexpected API request is made.
			assert.true(
				false,
				'Unexpected API request to ' + request.url + ' with request body ' +
					request.requestBody
			);
		}
	} );
	// Call the method under test
	postToRestApi( endpoint, id, dataContext ).then( ( data ) => {
		if ( shouldFail ) {
			assert.true( false, 'Request should have failed' );
		}
		assert.deepEqual( data, responseContent, 'Response data' );
		done();
	} ).fail( () => {
		if ( !shouldFail ) {
			assert.true( false, 'Request should have succeeded' );
		} else {
			assert.true( true, 'Request failed (expected)' );
		}
		done();
	} );
}

QUnit.test( 'Test postToRestApi for 500 response when requesting revision with ID 1 from infobox', ( assert ) => {
	commonPerformTest(
		assert, 'revision', 1, 'infobox',
		'ipinfo/v0/revision/1?dataContext=infobox&language=qqx', 500, '', true
	);
} );

QUnit.test( 'Test postToRestApi for 500 response when requesting log with ID 2 from popup', ( assert ) => {
	commonPerformTest(
		assert, 'log', 2, 'popup',
		'ipinfo/v0/log/2?dataContext=popup&language=qqx', 500, '', true
	);
} );

QUnit.test( 'Test postToRestApi for 200 response when requesting archivedrevision', ( assert ) => {
	commonPerformTest(
		assert, 'archivedrevision', 1, 'popup',
		'ipinfo/v0/archivedrevision/1?dataContext=popup&language=qqx', 200,
		{ test: 'test' }, false
	);
} );

QUnit.test( 'Test postToRestApi on bad CSRF token for both attempts', ( assert ) => {
	commonPerformTest(
		assert, 'log', 1, 'popup',
		'ipinfo/v0/log/1?dataContext=popup&language=qqx', 403,
		{ errorKey: 'rest-badtoken' }, true
	);
} );

QUnit.test( 'Test postToRestApi on bad CSRF token for first attempt', ( assert ) => {
	let csrfTokenUpdated = false;
	server.respond( ( request ) => {
		if ( request.url.endsWith( 'ipinfo/v0/revision/1?dataContext=infobox&language=qqx' ) ) {
			// If the CSRF token has been updated, then return a valid response. Otherwise, return a
			// response indicating that the CSRF token is invalid.
			if ( csrfTokenUpdated ) {
				request.respond(
					200, { 'Content-Type': 'application/json' }, JSON.stringify( { data: 'test' } )
				);
			} else {
				request.respond(
					400,
					{ 'Content-Type': 'application/json' },
					JSON.stringify( { errorKey: 'rest-badtoken' } )
				);
			}
		} else if (
			request.url.includes( 'type=csrf' ) &&
			request.url.includes( 'meta=tokens' ) &&
			!csrfTokenUpdated
		) {
			request.respond( 200, { 'Content-Type': 'application/json' }, JSON.stringify( {
				query: { tokens: { csrftoken: 'newtoken' } }
			} ) );
			csrfTokenUpdated = true;
		} else {
			// All API requests except the above are not expected to be called during the test.
			// To prevent the test from silently failing, we will fail the test if an
			// unexpected API request is made.
			assert.true( false, 'Unexpected API request to' + request.url );
		}
	} );
	// We need the test to wait a small amount of time for the fake requests to respond
	const done = assert.async();
	// Call the method under test
	postToRestApi( 'revision', 1, 'infobox' ).then( ( data ) => {
		assert.deepEqual( data, { data: 'test' }, 'Response data' );
		done();
	} ).fail( () => {
		assert.true( false, 'Request should have succeeded' );
		done();
	} );
} );
