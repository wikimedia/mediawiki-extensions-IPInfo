'use strict';

const { config } = require( 'wdio-mediawiki/wdio-defaults.conf.js' ),
	childProcess = require( 'child_process' ),
	path = require( 'path' ),
	ip = path.resolve( __dirname + '/../../../../' ),
	utils = require( './utils' ),
	LocalSettingsSetup = require( './LocalSettingsSetup' );

const { SevereServiceError } = require( 'webdriverio' );

exports.config = { ...config,
	// Override, or add to, the setting from wdio-mediawiki.
	// Learn more at https://webdriver.io/docs/configurationfile/
	//
	// Example:
	// logLevel: 'info',
	maxInstances: 4,

	async onPrepare() {
		await LocalSettingsSetup.overrideLocalSettings();
		await LocalSettingsSetup.restartPhpFpmService();

		// Setup required content and data.
		const populateTestDataResult = childProcess.spawnSync(
			'php',
			[
				'maintenance/run.php',
				'IPInfo:PopulateTestData',
				'--ip',
				utils.IP_WITH_EDITS,
				'--temp-name',
				utils.TEMP_USER_NAME
			],
			{ cwd: ip }
		);
		if ( populateTestDataResult.status === 1 ) {
			console.log( String( populateTestDataResult.stderr ) );
			throw new SevereServiceError( 'Unable to populate test data' );
		}
	},

	async onComplete() {
		await LocalSettingsSetup.restoreLocalSettings();
		await LocalSettingsSetup.restartPhpFpmService();
	}
};
