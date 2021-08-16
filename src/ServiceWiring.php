<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\InfoRetriever\GeoIp2InfoRetriever;
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
