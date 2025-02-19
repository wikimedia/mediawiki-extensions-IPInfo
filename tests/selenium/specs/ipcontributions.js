'use strict';

const utils = require( '../utils' ),
	Api = require( 'wdio-mediawiki/Api' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage' ),
	IPContributionsWithIPInfoPage = require( '../pageobjects/IPContributionsWithIPInfoPage' );

const ipContributionsWithIPInfoPage = new IPContributionsWithIPInfoPage();

describe( 'IPInfo on Special:IPContributions', () => {
	let bot;

	before( async () => {
		bot = await Api.bot();

		const rightsResponse = await bot.request( {
			action: 'query',
			meta: 'tokens',
			type: 'userrights'
		} );

		await bot.request( {
			action: 'userrights',
			user: bot.options.username,
			token: rightsResponse.query.tokens.userrightstoken,
			bot: true,
			add: [
				'checkuser',
				'checkuser-temporary-account',
				'checkuser-temporary-account-no-preference', // needed by CheckUser
				'ipinfo',
				'ipinfo-view-full'
			].join( '|' ),
			reason: 'Selenium testing'
		} );

		await bot.request( {
			action: 'options',
			change: 'checkuser-temporary-account-enable=1',
			format: 'json',
			formatversion: '2',
			token: bot.editToken
		} );
	} );
	async function optOutOfAgreement() {
		await bot.request( {
			action: 'options',
			change: 'ipinfo-use-agreement=0|ipinfo-beta-feature-enable=1',
			format: 'json',
			formatversion: '2',
			token: bot.editToken
		} );
	}

	async function acceptAgreement() {
		await bot.request( {
			action: 'options',
			change: 'ipinfo-use-agreement=1|ipinfo-beta-feature-enable=1',
			format: 'json',
			formatversion: '2',
			token: bot.editToken
		} );
	}

	it( 'should not be shown to users without necessary permissions', async () => {
		await ipContributionsWithIPInfoPage.open( utils.IP_WITHOUT_EDITS );
		await expect( ipContributionsWithIPInfoPage.ipInfoPanel ).not.toExist();
	} );

	it( 'should show an error for IPs without edits after accepting agreement', async () => {
		await LoginPage.loginAdmin();
		await optOutOfAgreement();

		await ipContributionsWithIPInfoPage.open( utils.IP_WITHOUT_EDITS );
		await ipContributionsWithIPInfoPage.expandPanel();
		await ipContributionsWithIPInfoPage.acceptAgreement();

		await expect( ipContributionsWithIPInfoPage.propertiesTable ).not.toExist();
		await expect( ipContributionsWithIPInfoPage.errorMessage ).toHaveText(
			'IP information for this address cannot be retrieved since no contributions have been made from it.'
		);
	} );

	it( 'should show geo data for IP with edits', async () => {
		await LoginPage.loginAdmin();
		await acceptAgreement();

		await ipContributionsWithIPInfoPage.open( utils.IP_WITH_EDITS );
		await ipContributionsWithIPInfoPage.expandPanel();
		await ipContributionsWithIPInfoPage.propertiesTable.waitForDisplayed();

		await expect( ipContributionsWithIPInfoPage.errorMessage ).not.toExist();

		await expect( await ipContributionsWithIPInfoPage.getPropertyValue( 'version' ) ).toBe( 'IPv4' );
		await expect( await ipContributionsWithIPInfoPage.hasProperty( 'num-ip-addresses' ) ).toBe( true );
		await expect( await ipContributionsWithIPInfoPage.getPropertyValue( 'asn' ) ).toBe( '721' );
		await expect( await ipContributionsWithIPInfoPage.getPropertyValue( 'organization' ) )
			.toBe( 'DoD Network Information Center' );
	} );
} );
