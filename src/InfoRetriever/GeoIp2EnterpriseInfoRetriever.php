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
use MediaWiki\User\UserIdentity;

/**
 * Manager for getting information from the MaxMind GeoIp2 Enterprise database.
 */
class GeoIp2EnterpriseInfoRetriever extends BaseInfoRetriever {
	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'IPInfoGeoIP2EnterprisePath',
	];

	public const NAME = 'ipinfo-source-geoip2';

	private ServiceOptions $options;

	private ReaderFactory $readerFactory;

	public function __construct(
		ServiceOptions $options,
		ReaderFactory $readerFactory
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->readerFactory = $readerFactory;
	}

	/** @inheritDoc */
	public function getName(): string {
		return self::NAME;
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
	public function retrieveFor( UserIdentity $user, ?string $ip ): Info {
		if ( $ip === null ) {
			return new Info();
		}

		$info = array_fill_keys(
			[
				'coordinates',
				'asn',
				'organization',
				'countryNames',
				'locations',
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
				$info['countryNames'] = $this->getCountryNames( $enterpriseInfo );
				$info['locations'] = $this->getLocations( $enterpriseInfo );
				$info['connectionType'] = $this->getConnectionType( $enterpriseInfo );
				$info['userType'] = $this->getUserType( $enterpriseInfo );
			} catch ( AddressNotFoundException ) {
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
			} catch ( AddressNotFoundException ) {
				// No need to do anything if it fails
				// $info defaults to null values
			}
		}

		return new Info(
			$info['coordinates'],
			$info['asn'],
			$info['organization'],
			$info['countryNames'],
			$info['locations'],
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
	 * @return array<string,string>|null null if this IP address does not return a country
	 */
	private function getCountryNames( Enterprise $info ): ?array {
		return $info->country->names;
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
