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
 * Convenience function to create something resembling real MediaWiki user links
 * from a list of usernames.
 *
 * @param {string[]} userNames
 * @return {jQuery[]}
 */
const createUserLinks = ( userNames ) => userNames.map( ( userName ) => {
	const $userLink = $( '<a>' )
		.addClass( 'mw-userlink' )
		.text( userName );

	if ( mw.util.isTemporaryUser( userName ) ) {
		$userLink.addClass( 'mw-tempuserlink' );
	} else if ( mw.util.isIPAddress( userName ) ) {
		$userLink.addClass( 'mw-anonuserlink' );
	}

	return $userLink;
} );

const testCases = {
	'revision rows': [ 'data-mw-revid' ],
	'log entries': [ 'data-mw-logid' ]
};

Object.entries( testCases ).forEach( ( [ testName, params ] ) => {
	const [ dataAttr ] = params;

	QUnit.test( `adds IPInfo button to temporary and anonymous user links in ${ testName }`, function ( assert ) {
		// eslint-disable-next-line no-jquery/no-global-selector
		const $content = $( '#qunit-fixture' );

		this.isTemporaryUser.withArgs( sinon.match.same( '~1' ) )
			.returns( true );
		this.isTemporaryUser.withArgs( sinon.match.same( '127.0.0.1' ).or( sinon.match.same( 'TestUser' ) ) )
			.returns( false );
		this.mwConfigGet.withArgs( 'wgCheckUserIsPerformerBlocked' )
			.returns( false );

		const rows = createUserLinks( [ '~1', '127.0.0.1', 'TestUser' ] )
			.map( ( $userLink, i ) => $( '<span>' ).attr( dataAttr, i + 1 ).append( $userLink ) );

		rows.forEach( ( $row ) => $content.append( $row ) );

		mw.hook( 'wikipage.content' ).fire( $content );

		rows.forEach( ( $row ) => {
			const $ipInfoButton = $row.find( '.ext-ipinfo-button' );
			const $userLink = $row.find( '.mw-userlink' );
			const userName = $userLink.text();

			if ( userName === 'TestUser' ) {
				assert.strictEqual( $ipInfoButton.length, 0, 'IPInfo button is not added for registered user link' );

				assert.strictEqual(
					// eslint-disable-next-line no-jquery/no-class-state
					$userLink.hasClass( 'ext-ipinfo-anonuserlink-loaded' ),
					false,
					'Registered user link should not be marked as processed'
				);
			} else {
				assert.strictEqual(
					$ipInfoButton.length,
					1,
					'IPInfo button is added for temporary and anonymous user links'
				);

				assert.strictEqual(
					// eslint-disable-next-line no-jquery/no-class-state
					$userLink.hasClass( 'ext-ipinfo-anonuserlink-loaded' ),
					true,
					'Temporary and anonymous user links should be marked as processed'
				);
			}
		} );
	} );

	QUnit.test( `adds IPInfo button to anonymous user links only in ${ testName } for blocked user with CU`,
		function ( assert ) {
			// eslint-disable-next-line no-jquery/no-global-selector
			const $content = $( '#qunit-fixture' );

			this.isTemporaryUser.withArgs( sinon.match.same( '~1' ) )
				.returns( true );
			this.isTemporaryUser.withArgs( sinon.match.same( '127.0.0.1' ).or( sinon.match.same( 'TestUser' ) ) )
				.returns( false );
			this.mwConfigGet.withArgs( 'wgCheckUserIsPerformerBlocked' )
				.returns( true );

			const rows = createUserLinks( [ '~1', '127.0.0.1', 'TestUser' ] )
				.map( ( $userLink, i ) => $( '<span>' ).attr( 'data-mw-revid', i + 1 ).append( $userLink ) );

			rows.forEach( ( $row ) => $content.append( $row ) );

			mw.hook( 'wikipage.content' ).fire( $content );

			rows.forEach( ( $row ) => {
				const $ipInfoButton = $row.find( '.ext-ipinfo-button' );
				const $userLink = $row.find( '.mw-userlink' );
				const userName = $userLink.text();

				if ( userName === 'TestUser' ) {
					assert.strictEqual(
						// eslint-disable-next-line no-jquery/no-class-state
						$userLink.hasClass( 'ext-ipinfo-anonuserlink-loaded' ),
						false,
						'Registered user link should not be marked as processed'
					);
				} else {
					assert.strictEqual(
						// eslint-disable-next-line no-jquery/no-class-state
						$userLink.hasClass( 'ext-ipinfo-anonuserlink-loaded' ),
						true,
						'Temporary and anonymous user links should be marked as processed'
					);
				}

				if ( userName === '127.0.0.1' ) {
					assert.strictEqual(
						$ipInfoButton.length,
						1,
						'IPInfo button is added for anonymous user links only'
					);
				} else {
					assert.strictEqual(
						$ipInfoButton.length,
						0,
						'IPInfo button is not added for registered or temporary user links'
					);
				}
			} );
		}
	);
} );
