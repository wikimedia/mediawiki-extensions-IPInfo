'use strict';

const utils = require( '../utils' ),
	Api = require( 'wdio-mediawiki/Api' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage' ),
	ContributionsWithIPInfoPage = require( '../pageobjects/ContributionsWithIPInfoPage' );

describe( 'IPInfo on Special:Contributions', () => {
	async function optOutOfAgreement() {
		const bot = await Api.bot();

		await bot.request( {
			action: 'options',
			change: 'ipinfo-use-agreement=0|ipinfo-beta-feature-enable=1',
			format: 'json',
			formatversion: '2',
			token: bot.editToken
		} );
	}

	async function acceptAgreement() {
		const bot = await Api.bot();

		await bot.request( {
			action: 'options',
			change: 'ipinfo-use-agreement=1|ipinfo-beta-feature-enable=1',
			format: 'json',
			formatversion: '2',
			token: bot.editToken
		} );
	}

	it( 'should not be shown to users without necessary permissions', async () => {
		await ContributionsWithIPInfoPage.open( utils.IP_WITHOUT_EDITS );
		await expect( ContributionsWithIPInfoPage.ipInfoPanel ).not.toExist();
	} );

	it( 'should display error message for IPs without edits after accepting agreement', async () => {
		await optOutOfAgreement();

		await LoginPage.loginAdmin();
		await ContributionsWithIPInfoPage.open( utils.IP_WITHOUT_EDITS );
		await ContributionsWithIPInfoPage.expandPanel();
		await ContributionsWithIPInfoPage.acceptAgreement();

		await expect( ContributionsWithIPInfoPage.propertiesTable ).not.toExist();
		await expect( ContributionsWithIPInfoPage.errorMessage ).toHaveText(
			'IP information for this address cannot be retrieved since no edits have been made from it.'
		);
	} );

	it( 'should display error message for IPs without edits if agreement was already accepted', async () => {
		await acceptAgreement();

		await LoginPage.loginAdmin();
		await ContributionsWithIPInfoPage.open( utils.IP_WITHOUT_EDITS );
		await ContributionsWithIPInfoPage.expandPanel();

		await expect( ContributionsWithIPInfoPage.propertiesTable ).not.toExist();
		await expect( ContributionsWithIPInfoPage.errorMessage ).toHaveText(
			'IP information for this address cannot be retrieved since no edits have been made from it.'
		);
	} );

	it( 'should show geo data for IP with edits after accepting agreement', async () => {
		await optOutOfAgreement();

		await LoginPage.loginAdmin();
		await ContributionsWithIPInfoPage.open( utils.IP_WITH_EDITS );
		await ContributionsWithIPInfoPage.expandPanel();
		await ContributionsWithIPInfoPage.acceptAgreement();
		await ContributionsWithIPInfoPage.propertiesTable.waitForDisplayed();

		await expect( ContributionsWithIPInfoPage.errorMessage ).not.toExist();

		await expect( await ContributionsWithIPInfoPage.hasProperty( 'number-of-ips' ) ).toBe( false );
		await expect( await ContributionsWithIPInfoPage.getPropertyValue( 'asn' ) ).toBe( '721' );
		await expect( await ContributionsWithIPInfoPage.getPropertyValue( 'organization' ) )
			.toBe( 'DoD Network Information Center' );
	} );

	it( 'should show geo data for IP with edits if agreement was already accepted', async () => {
		await acceptAgreement();

		await LoginPage.loginAdmin();
		await ContributionsWithIPInfoPage.open( utils.IP_WITH_EDITS );
		await ContributionsWithIPInfoPage.expandPanel();
		await ContributionsWithIPInfoPage.propertiesTable.waitForDisplayed();

		await expect( ContributionsWithIPInfoPage.errorMessage ).not.toExist();

		await expect( await ContributionsWithIPInfoPage.hasProperty( 'num-ip-addresses' ) ).toBe( false );
		await expect( await ContributionsWithIPInfoPage.getPropertyValue( 'asn' ) ).toBe( '721' );
		await expect( await ContributionsWithIPInfoPage.getPropertyValue( 'organization' ) )
			.toBe( 'DoD Network Information Center' );
	} );

	it( 'should show geo data for temp user with edits after accepting agreement', async () => {
		await optOutOfAgreement();

		await LoginPage.loginAdmin();
		await ContributionsWithIPInfoPage.open( utils.TEMP_USER_NAME );
		await ContributionsWithIPInfoPage.expandPanel();
		await ContributionsWithIPInfoPage.acceptAgreement();
		await ContributionsWithIPInfoPage.propertiesTable.waitForDisplayed();

		await expect( ContributionsWithIPInfoPage.errorMessage ).not.toExist();

		await expect( await ContributionsWithIPInfoPage.getPropertyValue( 'asn' ) ).toBe( '721' );
		await expect( await ContributionsWithIPInfoPage.getPropertyValue( 'organization' ) )
			.toBe( 'DoD Network Information Center' );
		await expect( await ContributionsWithIPInfoPage.getPropertyValue( 'num-ip-addresses' ) )
			.toBe( '1' );
	} );

	it( 'should show geo data for temp user with edits if agreement was already accepted', async () => {
		await acceptAgreement();

		await LoginPage.loginAdmin();
		await ContributionsWithIPInfoPage.open( utils.TEMP_USER_NAME );
		await ContributionsWithIPInfoPage.expandPanel();
		await ContributionsWithIPInfoPage.propertiesTable.waitForDisplayed();

		await expect( ContributionsWithIPInfoPage.errorMessage ).not.toExist();

		await expect( await ContributionsWithIPInfoPage.getPropertyValue( 'asn' ) ).toBe( '721' );
		await expect( await ContributionsWithIPInfoPage.getPropertyValue( 'organization' ) )
			.toBe( 'DoD Network Information Center' );
		await expect( await ContributionsWithIPInfoPage.getPropertyValue( 'num-ip-addresses' ) )
			.toBe( '1' );
	} );
} );
