<?php

use MediaWiki\IPInfo\InfoManager;
use MediaWiki\MediaWikiServices;

return [
	'IPInfoInfoManager' => function ( MediaWikiServices $services ) : InfoManager {
		return new InfoManager();
	}
];
