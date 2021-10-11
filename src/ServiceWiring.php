<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\InfoRetriever\BlockInfoRetriever;
use MediaWiki\IPInfo\InfoRetriever\ContributionInfoRetriever;
use MediaWiki\IPInfo\InfoRetriever\GeoIp2EnterpriseInfoRetriever;
use MediaWiki\IPInfo\InfoRetriever\GeoIp2InfoRetriever;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\ILoadBalancer;

return [
	'IPInfoGeoIp2InfoRetriever' => static function ( MediaWikiServices $services ) {
		$config = $services->getMainConfig();
		if ( $config->get( 'IPInfoGeoIP2EnterprisePath' ) ) {
			return new GeoIp2EnterpriseInfoRetriever(
				new ServiceOptions(
					GeoIp2EnterpriseInfoRetriever::CONSTRUCTOR_OPTIONS, $config
				)
			);
		}
		return new GeoIp2InfoRetriever(
			new ServiceOptions(
				GeoIp2InfoRetriever::CONSTRUCTOR_OPTIONS, $config
			)
		);
	},
	'IPInfoBlockInfoRetriever' => static function ( MediaWikiServices $services ): BlockInfoRetriever {
		$database = $services->getDBLoadBalancer()
			->getConnectionRef( ILoadBalancer::DB_REPLICA );

		return new BlockInfoRetriever( $services->getBlockManager(), $database );
	},
	'IPInfoContributionInfoRetriever' => static function ( MediaWikiServices $services ): ContributionInfoRetriever {
		$database = $services->getDBLoadBalancer()
			->getConnectionRef( ILoadBalancer::DB_REPLICA );

		return new ContributionInfoRetriever( $database );
	},
	'IPInfoInfoManager' => static function ( MediaWikiServices $services ): InfoManager {
		return new InfoManager( [
			$services->get( 'IPInfoGeoIp2InfoRetriever' ),
			$services->get( 'IPInfoBlockInfoRetriever' ),
			$services->get( 'IPInfoContributionInfoRetriever' ),
		] );
	}
];
