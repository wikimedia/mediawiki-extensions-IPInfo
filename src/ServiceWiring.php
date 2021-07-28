<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\InfoRetriever\BlockInfoRetriever;
use MediaWiki\IPInfo\InfoRetriever\GeoIp2InfoRetriever;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\ILoadBalancer;

return [
	'IPInfoGeoIp2InfoRetriever' => static function ( MediaWikiServices $services ): GeoIp2InfoRetriever {
		return new GeoIp2InfoRetriever(
			new ServiceOptions(
				GeoIp2InfoRetriever::CONSTRUCTOR_OPTIONS, $services->getMainConfig()
			)
		);
	},
	'IPInfoBlockInfoRetriever' => static function ( MediaWikiServices $services ): BlockInfoRetriever {
		$database = $services->getDBLoadBalancer()
			->getConnectionRef( ILoadBalancer::DB_REPLICA );

		return new BlockInfoRetriever( $services->getBlockManager(), $database );
	},
	'IPInfoInfoManager' => static function ( MediaWikiServices $services ): InfoManager {
		return new InfoManager( [
			$services->get( 'IPInfoGeoIp2InfoRetriever' ),
			$services->get( 'IPInfoBlockInfoRetriever' ),
		] );
	}
];
