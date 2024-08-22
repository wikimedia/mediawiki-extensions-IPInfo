'use strict';

const assert = require( 'assert' ),
	utils = require( '../utils' ),
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
		assert.ok( !await ContributionsWithIPInfoPage.ipInfoPanel.isExisting() );
	} );

	it( 'should display error message for IPs without edits after accepting agreement', async () => {
		await optOutOfAgreement();

		await LoginPage.loginAdmin();
		await ContributionsWithIPInfoPage.open( utils.IP_WITHOUT_EDITS );
		await ContributionsWithIPInfoPage.expandPanel();
		await ContributionsWithIPInfoPage.acceptAgreement();

		assert.ok( !await ContributionsWithIPInfoPage.propertiesTable.isExisting() );
		assert.strictEqual(
			await ContributionsWithIPInfoPage.errorMessage.getText(),
			'IP information for this address cannot be retrieved since no edits have been made from it.'
		);
	} );

	it( 'should display error message for IPs without edits if agreement was already accepted', async () => {
		await acceptAgreement();

		await LoginPage.loginAdmin();
		await ContributionsWithIPInfoPage.open( utils.IP_WITHOUT_EDITS );
		await ContributionsWithIPInfoPage.expandPanel();

		assert.ok( !await ContributionsWithIPInfoPage.propertiesTable.isExisting() );
		assert.strictEqual(
			await ContributionsWithIPInfoPage.errorMessage.getText(),
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

		assert.ok( !await ContributionsWithIPInfoPage.errorMessage.isExisting() );

		assert.ok( !await ContributionsWithIPInfoPage.hasProperty( 'number-of-ips' ) );
		assert.strictEqual(
			await ContributionsWithIPInfoPage.getPropertyValue( 'asn' ),
			'721'
		);
		assert.strictEqual(
			await ContributionsWithIPInfoPage.getPropertyValue( 'organization' ),
			'DoD Network Information Center'
		);
	} );

	it( 'should show geo data for IP with edits if agreement was already accepted', async () => {
		await acceptAgreement();

		await LoginPage.loginAdmin();
		await ContributionsWithIPInfoPage.open( utils.IP_WITH_EDITS );
		await ContributionsWithIPInfoPage.expandPanel();
		await ContributionsWithIPInfoPage.propertiesTable.waitForDisplayed();

		assert.ok( !await ContributionsWithIPInfoPage.errorMessage.isExisting() );

		assert.ok( !await ContributionsWithIPInfoPage.hasProperty( 'number-of-ips' ) );
		assert.strictEqual(
			await ContributionsWithIPInfoPage.getPropertyValue( 'asn' ),
			'721'
		);
		assert.strictEqual(
			await ContributionsWithIPInfoPage.getPropertyValue( 'organization' ),
			'DoD Network Information Center'
		);
	} );

	it( 'should show geo data for temp user with edits after accepting agreement', async () => {
		await optOutOfAgreement();

		await LoginPage.loginAdmin();
		await ContributionsWithIPInfoPage.open( utils.TEMP_USER_NAME );
		await ContributionsWithIPInfoPage.expandPanel();
		await ContributionsWithIPInfoPage.acceptAgreement();
		await ContributionsWithIPInfoPage.propertiesTable.waitForDisplayed();

		assert.ok( !await ContributionsWithIPInfoPage.errorMessage.isExisting() );
		assert.strictEqual(
			await ContributionsWithIPInfoPage.getPropertyValue( 'asn' ),
			'721'
		);
		assert.strictEqual(
			await ContributionsWithIPInfoPage.getPropertyValue( 'organization' ),
			'DoD Network Information Center'
		);
		assert.strictEqual(
			await ContributionsWithIPInfoPage.getPropertyValue( 'num-ip-addresses' ),
			'1'
		);
	} );

	it( 'should show geo data for temp user with edits if agreement was already accepted', async () => {
		await acceptAgreement();

		await LoginPage.loginAdmin();
		await ContributionsWithIPInfoPage.open( utils.TEMP_USER_NAME );
		await ContributionsWithIPInfoPage.expandPanel();
		await ContributionsWithIPInfoPage.propertiesTable.waitForDisplayed();

		assert.ok( !await ContributionsWithIPInfoPage.errorMessage.isExisting() );
		assert.strictEqual(
			await ContributionsWithIPInfoPage.getPropertyValue( 'asn' ),
			'721'
		);
		assert.strictEqual(
			await ContributionsWithIPInfoPage.getPropertyValue( 'organization' ),
			'DoD Network Information Center'
		);
		assert.strictEqual(
			await ContributionsWithIPInfoPage.getPropertyValue( 'num-ip-addresses' ),
			'1'
		);
	} );
} );
