'use strict';

const assert = require( 'assert' ),
	Api = require( 'wdio-mediawiki/Api' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage' ),
	ContributionsWithIPInfoPage = require( '../pageobjects/ContributionsWithIPInfoPage' );

describe( 'IPInfo on Special:Contributions', () => {
	const ipWithoutEdits = '81.2.69.144';
	const ipWithEdits = '214.78.120.5';

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
		await ContributionsWithIPInfoPage.open( ipWithoutEdits );
		assert.ok( !await ContributionsWithIPInfoPage.ipInfoPanel.isExisting() );
	} );

	it( 'should display error message for IPs without edits after accepting agreement', async () => {
		await optOutOfAgreement();

		await LoginPage.loginAdmin();
		await ContributionsWithIPInfoPage.open( ipWithoutEdits );
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
		await ContributionsWithIPInfoPage.open( ipWithoutEdits );
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
		await ContributionsWithIPInfoPage.open( ipWithEdits );
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
	} );

	it( 'should show geo data for IP with edits if agreement was already accepted', async () => {
		await acceptAgreement();

		await LoginPage.loginAdmin();
		await ContributionsWithIPInfoPage.open( ipWithEdits );
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
	} );
} );
