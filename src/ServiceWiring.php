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
use Wikimedia\Rdbms\ILoadBalancer;

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
			$services->get( 'IPInfoGeoLite2InfoRetriever' ),
			$services->get( 'IPInfoBlockInfoRetriever' ),
			$services->get( 'IPInfoContributionInfoRetriever' ),
		] );
	},
	'IPInfoLoggerFactory' => static function ( MediaWikiServices $services ): LoggerFactory {
		$dbw = $services->getDBLoadBalancer()
			->getConnectionRef( ILoadBalancer::DB_PRIMARY );
		return new LoggerFactory(
			$services->getActorStore(),
			$dbw
		);
	},
	'ReaderFactory' => static function ( MediaWikiServices $services ) {
		return new ReaderFactory(
			$services->getLanguageFallback()
		);
	}
];

// @codeCoverageIgnoreEnd
