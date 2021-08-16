<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\IPInfo\Info\Asn;
use MediaWiki\IPInfo\Info\ConnectionType;
use MediaWiki\IPInfo\Info\Coordinates;
use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\Info\Isp;
use MediaWiki\IPInfo\Info\Location;
use MediaWiki\IPInfo\Info\Organization;
use MediaWiki\IPInfo\Info\ProxyType;

/**
 * Manager for getting information from the MaxMind GeoIp2 databases.
 */
class GeoIp2InfoRetriever implements InfoRetriever {
	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'IPInfoGeoIP2Path',
	];

	/** @var ServiceOptions */
	private $options;

	/** @var Reader[] Map of filename to Reader object */
	private $readers = [];

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
		if ( isset( $this->readers[$filename] ) ) {
			return $this->readers[$filename];
		}

		$path = $this->options->get( 'IPInfoGeoIP2Path' );

		if ( $path === false ) {
			return null;
		}

		try {
			$reader = new Reader( $path . $filename );
		} catch ( \Exception $e ) {
			return null;
		}

		$this->readers[$filename] = $reader;

		return $this->readers[$filename];
	}

	/**
	 * @inheritDoc
	 * @return Info
	 */
	public function retrieveFromIP( string $ip ) {
		return new Info(
			$this->getCoordinates( $ip ),
			$this->getAsn( $ip ),
			$this->getOrganization( $ip ),
			$this->getLocations( $ip ),
			$this->getIsp( $ip ),
			$this->getConnectionType( $ip ),
			$this->getProxyType( $ip )
		);
	}

	/**
	 * @param string $ip
	 * @return Coordinates|null null if IP address does not return a latitude/longitude
	 */
	private function getCoordinates( string $ip ): ?Coordinates {
		$reader = $this->getReader( 'City.mmdb' );
		if ( !$reader ) {
			return null;
		}

		try {
			$city = $reader->city( $ip );
		} catch ( AddressNotFoundException $e ) {
			return null;
		}

		$location = $city->location;
		if ( !$location->latitude || !$location->longitude ) {
			return null;
		}

		return new Coordinates(
			$location->latitude,
			$location->longitude
		);
	}

	/**
	 * @param string $ip
	 * @return Asn|null null if this IP address is not in the database
	 */
	private function getAsn( string $ip ): ?Asn {
		$reader = $this->getReader( 'ASN.mmdb' );
		if ( !$reader ) {
			return null;
		}

		try {
			$asn = $reader->asn( $ip );
		} catch ( AddressNotFoundException $e ) {
			return null;
		}

		return new Asn(
			$asn->autonomousSystemNumber
		);
	}

	/**
	 * @param string $ip
	 * @return Organization|null null if this IP address is not in the database
	 */
	private function getOrganization( string $ip ): ?Organization {
		$reader = $this->getReader( 'ASN.mmdb' );
		if ( !$reader ) {
			return null;
		}

		try {
			$asn = $reader->asn( $ip );
		} catch ( AddressNotFoundException $e ) {
			return null;
		}

		return new Organization(
			$asn->autonomousSystemOrganization
		);
	}

	/**
	 * @param string $ip
	 * @return Location[] Empty array if IP address does not return a city name
	 */
	private function getLocations( string $ip ): array {
		$reader = $this->getReader( 'City.mmdb' );
		if ( !$reader ) {
			return [];
		}

		try {
			$city = $reader->city( $ip );
		} catch ( AddressNotFoundException $e ) {
			return [];
		}

		if ( !$city->city->geonameId || !$city->city->name ) {
			return [];
		}

		$locations = [ new Location(
			$city->city->geonameId,
			$city->city->name
		) ];

		return array_merge( $locations, array_map(
			static function ( $subdivision ) {
				return new Location(
					$subdivision->geonameId,
					$subdivision->name
				);
			},
			$city->subdivisions
		) );
	}

	/**
	 * @param string $ip
	 * @return Isp|null null if IP address does not return an ISP
	 */
	private function getIsp( string $ip ): ?Isp {
		$reader = $this->getReader( 'ISP.mmdb' );
		if ( !$reader ) {
			return null;
		}

		try {
			$isp = $reader->isp( $ip );
		} catch ( AddressNotFoundException $e ) {
			return null;
		}

		if ( $isp->isp === null ) {
			return null;
		}

		return new Isp(
			$isp->isp
		);
	}

	/**
	 * @param string $ip
	 * @return ConnectionType|null null if IP address does not return a
	 *  ConnectionType
	 */
	private function getConnectionType( string $ip ): ?ConnectionType {
		$reader = $this->getReader( 'Connection-Type.mmdb' );
		if ( !$reader ) {
			return null;
		}

		try {
			$connectionType = $reader->connectionType( $ip );
		} catch ( AddressNotFoundException $e ) {
			return null;
		}

		if ( $connectionType->connectionType === null ) {
			return null;
		}

		return new ConnectionType(
			$connectionType->connectionType
		);
	}

	/**
	 * @param string $ip
	 * @return ProxyType|null null if reader does not exist or if traits cannot be accessed
	 */
	private function getProxyType( string $ip ): ?ProxyType {
		$reader = $this->getReader( 'City.mmdb' );
		if ( !$reader ) {
			return null;
		}

		try {
			$city = $reader->city( $ip );
		} catch ( AddressNotFoundException $e ) {
			return null;
		}

		$traits = $city->traits;
		if ( !$traits ) {
			return null;
		}

		// GeoIP only returns these traits if they exist and always returns true if they do
		return new ProxyType(
			$traits->isAnonymous ?? false,
			$traits->isAnonymousVpn ?? false,
			$traits->isPublicProxy ?? false,
			$traits->isResidentialProxy ?? false,
			$traits->isLegitimateProxy ?? false,
			$traits->isTorExitNode ?? false
		);
	}
}
