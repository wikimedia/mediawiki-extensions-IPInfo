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

	/** @var ReaderFactory */
	private $readerFactory;

	/**
	 * @param ServiceOptions $options
	 * @param ReaderFactory $readerFactory
	 */
	public function __construct(
		ServiceOptions $options,
		ReaderFactory $readerFactory
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->readerFactory = $readerFactory;
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
	 * @codeCoverageIgnore tested when retrieveFromIP is run
	 */
	public function getReader( string $filename ): ?Reader {
		$path = $this->options->get( 'IPInfoGeoIP2EnterprisePath' );

		if ( $path === false ) {
			return null;
		}

		return $this->readerFactory->get( $path, $filename );
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
				'country',
				'locations',
				'isp',
				'connectionType',
				'userType',
				'proxyType',
			],
			null
		);

		$enterpriseReader = $this->getReader( 'GeoIP2-Enterprise.mmdb' );
		if ( $enterpriseReader ) {
			try {
				$enterpriseInfo = $enterpriseReader->enterprise( $ip );

				$info['coordinates'] = $this->getCoordinates( $enterpriseInfo );
				$info['asn'] = $this->getAsn( $enterpriseInfo );
				$info['organization'] = $this->getOrganization( $enterpriseInfo );
				$info['country'] = $this->getCountry( $enterpriseInfo );
				$info['locations'] = $this->getLocations( $enterpriseInfo );
				$info['isp'] = $this->getIsp( $enterpriseInfo );
				$info['connectionType'] = $this->getConnectionType( $enterpriseInfo );
				$info['userType'] = $this->getUserType( $enterpriseInfo );
			} catch ( AddressNotFoundException $e ) {
				// No need to do anything if it fails
				// $info defaults to null values
			}
		}

		$anonymousIpReader = $this->getReader( 'GeoIP2-Anonymous-IP.mmdb' );
		if ( $anonymousIpReader ) {
			try {
				$anonymousIpInfo = $anonymousIpReader->anonymousIp( $ip );
				$isLegitimateProxy = null;
				if ( isset( $enterpriseInfo ) ) {
					$isLegitimateProxy = (bool)$enterpriseInfo->traits->isLegitimateProxy;
				}
				$info['proxyType'] = $this->getProxyType( $anonymousIpInfo, $isLegitimateProxy );
			} catch ( AddressNotFoundException $e ) {
				// No need to do anything if it fails
				// $info defaults to null values
			}
		}

		return new Info(
			$info['coordinates'],
			$info['asn'],
			$info['organization'],
			$info['country'],
			$info['locations'],
			$info['isp'],
			$info['connectionType'],
			$info['userType'],
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
	 * @return int|null null if this IP address does not return an ASN
	 */
	private function getAsn( Enterprise $info ): ?int {
		return $info->traits->autonomousSystemNumber;
	}

	/**
	 * @param Enterprise $info
	 * @return string|null null if this IP address does not return an organization
	 */
	private function getOrganization( Enterprise $info ): ?string {
		return $info->traits->autonomousSystemOrganization;
	}

	/**
	 * @param Enterprise $info
	 * @return Location[]|null null if this IP address does not return a country
	 */
	private function getCountry( Enterprise $info ): ?array {
		if ( !$info->country->geonameId || !$info->country->name ) {
			return null;
		}

		return [ new Location(
			$info->country->geonameId,
			$info->country->name
		) ];
	}

	/**
	 * @param Enterprise $info
	 * @return Location[]|null null if this IP address does not return a location
	 */
	private function getLocations( Enterprise $info ): ?array {
		if ( !$info->city->geonameId || !$info->city->name ) {
			return null;
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
	public function getConnectionType( Enterprise $info ): ?string {
		return $info->traits->connectionType;
	}

	/**
	 * @param Enterprise $info
	 * @return string|null null if GeoIP2 does not return a connection type
	 */
	private function getUserType( Enterprise $info ): ?string {
		return $info->traits->userType;
	}

	/**
	 * @param AnonymousIp $anonymousIpinfo
	 * @param bool|null $isLegitimateProxy
	 * @return ProxyType
	 */
	private function getProxyType( AnonymousIp $anonymousIpinfo, ?bool $isLegitimateProxy ): ProxyType {
		return new ProxyType(
			(bool)$anonymousIpinfo->isAnonymousVpn || null,
			(bool)$anonymousIpinfo->isPublicProxy || null,
			(bool)$anonymousIpinfo->isResidentialProxy || null,
			$isLegitimateProxy,
			(bool)$anonymousIpinfo->isTorExitNode || null,
			(bool)$anonymousIpinfo->isHostingProvider || null
		);
	}
}
