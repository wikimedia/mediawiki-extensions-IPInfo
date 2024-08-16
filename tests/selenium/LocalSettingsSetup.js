'use strict';

const childProcess = require( 'child_process' ),
	process = require( 'process' ),
	phpVersion = process.env.PHP_VERSION,
	phpFpmService = 'php' + phpVersion + '-fpm',
	fs = require( 'fs' ),
	path = require( 'path' ),
	ip = path.resolve( __dirname + '/../../../../' ),
	localSettingsPath = path.resolve( ip + '/LocalSettings.php' ),
	localSettingsContents = fs.readFileSync( localSettingsPath );

/**
 * This is needed in Quibble + Apache (T225218) because we use supervisord to control
 * the php-fpm service, and with supervisord you need to restart the php-fpm service
 * in order to load updated php code.
 */
async function restartPhpFpmService() {
	if ( !process.env.QUIBBLE_APACHE ) {
		return;
	}
	console.log( 'Restarting ' + phpFpmService );
	childProcess.spawnSync(
		'service',
		[ phpFpmService, 'restart' ]
	);
	// Ugly hack: Run this twice because sometimes the first invocation hangs.
	childProcess.spawnSync(
		'service',
		[ phpFpmService, 'restart' ]
	);
}

/**
 * Require the GrowthExperiments.LocalSettings.php in the main LocalSettings.php. Note that you
 * need to call restartPhpFpmService for this take effect in a Quibble environment.
 */
async function overrideLocalSettings() {
	console.log( 'Setting up modified ' + localSettingsPath );
	const extraSettingsPath = path.resolve( __dirname + '/../fixtures/ExtraLocalSettings.php' );

	fs.writeFileSync( localSettingsPath,
		localSettingsContents + `
if ( is_readable( "${ extraSettingsPath }" ) ) {
	require_once "${ extraSettingsPath }";
}
` );
}

/**
 * Restore the original, unmodified LocalSettings.php.
 *
 * Note that you need to call restartPhpFpmService for this to take effect in a
 * Quibble environment.
 */
async function restoreLocalSettings() {
	console.log( 'Restoring original ' + localSettingsPath );
	await fs.writeFileSync( localSettingsPath, localSettingsContents );
}

module.exports = { restartPhpFpmService, overrideLocalSettings, restoreLocalSettings };
