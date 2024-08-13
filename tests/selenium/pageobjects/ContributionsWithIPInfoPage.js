'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class ContributionsWithIPInfoPage extends Page {
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
	 * Get the value of the given property from the IPInfo data table.
	 *
	 * @param {string} propName
	 * @return {Promise<string>}
	 */
	async getPropertyValue( propName ) {
		return $( `[data-property=${ propName }] > dd` ).getText();
	}

	/**
	 * Open Special:Contributions for the given user.
	 *
	 * @param {string} target User name or IP address whose contributions to open
	 * @return {Promise<void>}
	 */
	async open( target ) {
		await super.openTitle( `Special:Contributions/${ target }` );
	}
}

module.exports = new ContributionsWithIPInfoPage();
