'use strict';

/**
 * Copied from mediawiki/extensions/CheckUser QUnit tests.
 */

/**
 * Waits until the specified selector appears in the QUnit test fixture.
 *
 * @param {string} selector The JQuery selector to check
 * @return {Promise}
 */
function waitUntilElementAppears( selector ) {
	return waitUntilElementCount( selector, 1 );
}

/**
 * Waits until the specified selector matches the specified count of elements in
 * the QUnit test fixture.
 *
 * @param {string} selector The JQuery selector to check
 * @param {number} count The number of elements to wait for
 * @return {Promise}
 */
function waitUntilElementCount( selector, count ) {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	return new Promise( ( resolve ) => {
		// Check every 10ms if the class matches any element in the QUnit test fixture.
		// If the class is no longer present, then resolve is called.
		// If this condition is not met ever, then QUnit will time the test out after 6s.
		function runCheck() {
			setTimeout( () => {
				if ( $( selector, $qunitFixture ).length === count ) {
					return resolve();
				}
				runCheck();
			}, 10 );
		}
		runCheck();
	} );
}

module.exports = {
	waitUntilElementAppears: waitUntilElementAppears
};
