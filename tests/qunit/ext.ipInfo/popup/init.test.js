'use strict';

QUnit.module( 'ext.ipInfo.popup.index', QUnit.newMwEnvironment( {
	beforeEach() {
		this.isTemporaryUser = sinon.stub( mw.util, 'isTemporaryUser' );
		this.mwConfigGet = sinon.stub( mw.config, 'get' );
	},

	afterEach() {
		this.isTemporaryUser.restore();
		this.mwConfigGet.restore();
	}
} ) );

/**
 * Convenience function to create something resembling a real MediaWiki user link
 * from a username.
 *
 * @param {string} userName
 * @return {jQuery[]}
 */
const createUserLink = ( userName ) => {
	if ( userName.startsWith( '>' ) ) {
		return $( '<span>' )
			.addClass( 'mw-userlink mw-anonuserlink' )
			.text( userName );
	}

	const $userLink = $( '<a>' )
		.addClass( 'mw-userlink' )
		.text( userName );

	if ( mw.util.isTemporaryUser( userName ) ) {
		$userLink.addClass( 'mw-tempuserlink' )
			.attr( 'data-mw-target', userName );
	} else if ( mw.util.isIPAddress( userName ) ) {
		$userLink.addClass( 'mw-anonuserlink' );
	}

	return $userLink;
};

const pagerTypeTestCases = {
	'revision rows': [ 'data-mw-revid' ],
	'log entries': [ 'data-mw-logid' ]
};

for ( const [ pagerType, [ dataAttr ] ] of Object.entries( pagerTypeTestCases ) ) {
	const testCases = {
		'registered user link': [ 'TestUser', false, false, false ],
		'temporary user link': [ '~1', false, true, true ],
		'temporary user link with blocked performer': [ '~1', true, true, false ],
		'IP user link': [ '127.0.0.1', false, true, true ],
		'IP user link with blocked performer': [ '127.0.0.1', false, true, true ],
		'external user link': [ '>ExternalUser', false, false, false ]
	};

	for ( const [
		testName, [ userName, isPerformedBlocked, shouldMarkProcessed, shouldAddButton ]
	] of Object.entries( testCases ) ) {
		QUnit.test( `${ testName } in ${ pagerType }`, function ( assert ) {
			// eslint-disable-next-line no-jquery/no-global-selector
			const $content = $( '#qunit-fixture' );

			this.isTemporaryUser.withArgs( sinon.match.same( '~1' ) )
				.returns( true );
			this.isTemporaryUser.withArgs( sinon.match.in( [ '127.0.0.1', '>ExternalUser', 'TestUser' ] ) )
				.returns( false );
			this.mwConfigGet.withArgs( 'wgCheckUserIsPerformerBlocked' )
				.returns( isPerformedBlocked );

			const $userLink = createUserLink( userName );
			const $row = $( '<span>' )
				.attr( dataAttr, '1' )
				.append( $userLink )
				.appendTo( $content );

			mw.hook( 'wikipage.content' ).fire( $content );

			assert.strictEqual(
				$row.find( '.ext-ipinfo-button' ).length,
				shouldAddButton ? 1 : 0,
				`IPInfo button should ${ shouldAddButton ? 'be' : 'not be' } added for ${ testName }`
			);

			assert.strictEqual(
				// eslint-disable-next-line no-jquery/no-class-state
				$userLink.hasClass( 'ext-ipinfo-anonuserlink-loaded' ),
				shouldMarkProcessed,
				`${ testName } should ${ shouldMarkProcessed ? 'be' : 'not be' } marked as processed`
			);
		} );

	}
}
