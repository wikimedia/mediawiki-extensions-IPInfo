<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\AnonymousIp;
use GeoIp2\Model\Enterprise;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\IPInfo\Info\Coordinates;
use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\Info\Location;
use MediaWiki\IPInfo\Info\ProxyType;

/**
 * Manager for getting information from the MaxMind GeoIp2 Enterprise database.
 */
class GeoIp2EnterpriseInfoRetriever implements InfoRetriever {
	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'IPInfoGeoIP2EnterprisePath',
	];

	/** @var ServiceOptions */
	private $options;

	/**
	 * @param ServiceOptions $options
	 */
	public function __construct(
		ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return 'ipinfo-source-geoip2';
	}

	/**
	 * @param string $filename
	 * @return Reader|null null if the file path or file is invalid
	 */
	private function getReader( string $filename ): ?Reader {
		$path = $this->options->get( 'IPInfoGeoIP2EnterprisePath' );

		if ( $path === false ) {
			return null;
		}

		try {
			$reader = new Reader( $path . $filename );
		} catch ( \Exception $e ) {
			return null;
		}

		return $reader;
	}

	/**
	 * @inheritDoc
	 * @return Info
	 */
	public function retrieveFromIP( string $ip ): Info {
		$info = array_fill_keys(
			[
				'coordinates',
				'asn',
				'organization',
				'isp',
				'connectionType',
				'proxyType',
			],
			null
		);
		$info['locations'] = [];

		$enterpriseReader = $this->getReader( 'GeoIP2-Enterprise.mmdb' );
		if ( $enterpriseReader ) {
			try {
				$enterpriseInfo = $enterpriseReader->enterprise( $ip );

				$info['coordinates'] = $this->getCoordinates( $enterpriseInfo );
				$info['asn'] = $this->getAsn( $enterpriseInfo );
				$info['organization'] = $this->getOrganization( $enterpriseInfo );
				$info['locations'] = $this->getLocations( $enterpriseInfo );
				$info['isp'] = $this->getIsp( $enterpriseInfo );
				$info['connectionType'] = $this->getConnectionType( $enterpriseInfo );
			} catch ( AddressNotFoundException $e ) {
				// No need to do anything if it fails
				// $info defaults to null values
			}
		}

		$anonymousIpReader = $this->getReader( 'GeoIP2-Anonymous-IP.mmdb' );
		if ( $anonymousIpReader ) {
			try {
				$anonymousIpInfo = $anonymousIpReader->anonymousIp( $ip );

				$info['proxyType'] = $this->getProxyType( $anonymousIpInfo );
			} catch ( AddressNotFoundException $e ) {
				// No need to do anything if it fails
				// $info defaults to null values
			}
		}

		return new Info(
			$info['coordinates'],
			$info['asn'],
			$info['organization'],
			$info['locations'],
			$info['isp'],
			$info['connectionType'],
			$info['proxyType']
		);
	}

	/**
	 * @param Enterprise $info
	 * @return Coordinates|null null if IP address does not return a latitude/longitude
	 */
	private function getCoordinates( Enterprise $info ): ?Coordinates {
		$location = $info->location;
		if ( !$location->latitude || !$location->longitude ) {
			return null;
		}

		return new Coordinates(
			$location->latitude,
			$location->longitude
		);
	}

	/**
	 * @param Enterprise $info
	 * @return int|null null if this IP address is not in the database
	 */
	private function getAsn( Enterprise $info ): ?int {
		return $info->traits->autonomousSystemNumber;
	}

	/**
	 * @param Enterprise $info
	 * @return string|null null if this IP address is not in the database
	 */
	private function getOrganization( Enterprise $info ): ?string {
		return $info->traits->autonomousSystemOrganization;
	}

	/**
	 * @param Enterprise $info
	 * @return Location[] Empty if this IP address is not in the database
	 */
	private function getLocations( Enterprise $info ): array {
		if ( !$info->city->geonameId || !$info->city->name ) {
			return [];
		}

		$locations = [ new Location(
			$info->city->geonameId,
			$info->city->name
		) ];

		/** MaxMind returns the locations sorted largest area to smallest.
		 * array_reverse is used to convert them to the preferred order of
		 * smallest to largest
		 */
		return array_merge( $locations, array_map(
			static function ( $subdivision ) {
				return new Location(
					$subdivision->geonameId,
					$subdivision->name
				);
			},
			array_reverse( $info->subdivisions )
		) );
	}

	/**
	 * @param Enterprise $info
	 * @return string|null null if GeoIP2 does not return an ISP
	 */
	private function getIsp( Enterprise $info ): ?string {
		return $info->traits->isp;
	}

	/**
	 * @param Enterprise $info
	 * @return string|null null if GeoIP2 does not return a connection type
	 */
	private function getConnectionType( Enterprise $info ): ?string {
		return $info->traits->connectionType;
	}

	/**
	 * @param AnonymousIp $info
	 * @return ProxyType|null null if reader does not exist or if traits cannot be accessed
	 */
	private function getProxyType( AnonymousIp $info ): ?ProxyType {
		return new ProxyType(
			$info->isAnonymous ?? false,
			$info->isAnonymousVpn ?? false,
			$info->isPublicProxy ?? false,
			$info->isResidentialProxy ?? false,
			$info->isLegitimateProxy ?? false,
			$info->isTorExitNode ?? false
		);
	}
}
