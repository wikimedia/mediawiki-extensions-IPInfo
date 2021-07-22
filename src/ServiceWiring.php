<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\IPInfo\GeoIp2InfoRetriever;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\MediaWikiServices;

return [
	'IPInfoGeoIp2InfoRetriever' => static function ( MediaWikiServices $services ): GeoIp2InfoRetriever {
		return new GeoIp2InfoRetriever(
			new ServiceOptions(
				GeoIp2InfoRetriever::CONSTRUCTOR_OPTIONS, $services->getMainConfig()
			)
		);
	},
	'IPInfoInfoManager' => static function ( MediaWikiServices $services ): InfoManager {
		return new InfoManager( [
			$services->get( 'IPInfoGeoIp2InfoRetriever' ),
		] );
	}
];
