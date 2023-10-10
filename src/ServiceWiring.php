<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\InfoRetriever\BlockInfoRetriever;
use MediaWiki\IPInfo\InfoRetriever\ContributionInfoRetriever;
use MediaWiki\IPInfo\InfoRetriever\GeoIp2EnterpriseInfoRetriever;
use MediaWiki\IPInfo\InfoRetriever\GeoLite2InfoRetriever;
use MediaWiki\IPInfo\InfoRetriever\ReaderFactory;
use MediaWiki\IPInfo\Logging\LoggerFactory;
use MediaWiki\MediaWikiServices;

// PHPUnit doesn't understand code coverage for code outside of classes/functions,
// like service wiring files. see T310509
// @codeCoverageIgnoreStart
return [
	'IPInfoGeoLite2InfoRetriever' => static function ( MediaWikiServices $services ) {
		$config = $services->getMainConfig();
		if ( $config->get( 'IPInfoGeoIP2EnterprisePath' ) ) {
			return new GeoIp2EnterpriseInfoRetriever(
				new ServiceOptions(
					GeoIp2EnterpriseInfoRetriever::CONSTRUCTOR_OPTIONS, $config
				),
				$services->get( 'ReaderFactory' )
			);
		}
		return new GeoLite2InfoRetriever(
			new ServiceOptions(
				GeoLite2InfoRetriever::CONSTRUCTOR_OPTIONS, $config
			),
			$services->get( 'ReaderFactory' )
		);
	},
	'IPInfoBlockInfoRetriever' => static function ( MediaWikiServices $services ): BlockInfoRetriever {
		return new BlockInfoRetriever( $services->getBlockManager() );
	},
	'IPInfoContributionInfoRetriever' => static function ( MediaWikiServices $services ): ContributionInfoRetriever {
		return new ContributionInfoRetriever( $services->getDBLoadBalancerFactory()->getReplicaDatabase() );
	},
	'IPInfoInfoManager' => static function ( MediaWikiServices $services ): InfoManager {
		return new InfoManager( [
			$services->get( 'IPInfoGeoLite2InfoRetriever' ),
			$services->get( 'IPInfoBlockInfoRetriever' ),
			$services->get( 'IPInfoContributionInfoRetriever' ),
		] );
	},
	'IPInfoLoggerFactory' => static function ( MediaWikiServices $services ): LoggerFactory {
		return new LoggerFactory(
			$services->getActorStore(),
			$services->getDBLoadBalancerFactory()->getPrimaryDatabase()
		);
	},
	'ReaderFactory' => static function () {
		return new ReaderFactory();
	}
];

// @codeCoverageIgnoreEnd
