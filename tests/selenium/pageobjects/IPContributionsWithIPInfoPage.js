'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class IPContributionsWithIPInfoPage extends Page {
	get ipInfoPanel() {
		return $( '.ext-ipinfo-panel-layout' );
	}

	get collapsibleToggle() {
		return this.ipInfoPanel.$( '.mw-collapsible-toggle' );
	}

	get collapsibleContent() {
		return this.ipInfoPanel.$( '.mw-collapsible-content' );
	}

	get ipInfoAgreeCheckbox() {
		return $( 'input[value=ipinfo-use-agreement]' );
	}

	get submitButton() {
		return $( 'button[name=submit-agreement]' );
	}

	get propertiesTable() {
		return $( '.ext-ipinfo-widget-properties' );
	}

	get errorMessage() {
		return this.ipInfoPanel.$( '[role=alert]' );
	}

	async expandPanel() {
		try {
			await this.collapsibleContent.waitForDisplayed();
		} catch ( e ) {
			await this.collapsibleToggle.waitForDisplayed();
			await this.collapsibleToggle.click();
		}
	}

	async acceptAgreement() {
		await this.submitButton.waitForDisplayed();
		await this.ipInfoAgreeCheckbox.click();
		await this.submitButton.click();
	}

	/**
	 * Check whether the given property exists in the IPInfo data table.
	 *
	 * @param {string} propName
	 * @return {Promise<boolean>}
	 */
	async hasProperty( propName ) {
		return $( `[data-property=${ propName }]` ).isExisting();
	}

	/**
	 * Get the value of the given property from the IPInfo data table.
	 *
	 * @param {string} propName
	 * @return {Promise<string>}
	 */
	async getPropertyValue( propName ) {
		return $( `[data-property=${ propName }] > dd` ).getText();
	}

	/**
	 * Open Special:IPContributions for the given user.
	 *
	 * @param {string} target IP address whose contributions to open
	 * @return {Promise<void>}
	 * @override
	 */
	async open( target ) {
		await super.openTitle( `Special:IPContributions/${ target }` );
	}
}

module.exports = IPContributionsWithIPInfoPage;
