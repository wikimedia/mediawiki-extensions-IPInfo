<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../EventLogging',
		'../GlobalPreferences',
		'../MobileFrontend',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../EventLogging',
		'../GlobalPreferences',
		'../MobileFrontend',
	]
);

return $cfg;
