'use strict';

const { config } = require( 'wdio-mediawiki/wdio-defaults.conf.js' ),
	childProcess = require( 'child_process' ),
	path = require( 'path' ),
	ip = path.resolve( __dirname + '/../../../../' ),
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

		// Import a test article that was edited by an anonymous user.
		const ipEditFixturePath = path.resolve( __dirname + '/../fixtures/ip-edit-fixture.xml' );
		console.log( 'Importing ' + ipEditFixturePath );
		const importDumpResult = await childProcess.spawnSync(
			'php',
			[ 'maintenance/run.php', 'importDump', ipEditFixturePath ],
			{ cwd: ip }
		);
		if ( importDumpResult.status === 1 ) {
			console.log( String( importDumpResult.stderr ) );
			throw new SevereServiceError( 'Unable to import ' + ipEditFixturePath );
		}
	},

	async onComplete() {
		await LocalSettingsSetup.restoreLocalSettings();
		await LocalSettingsSetup.restartPhpFpmService();
	}
};
