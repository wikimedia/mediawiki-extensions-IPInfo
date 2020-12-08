<?php

namespace MediaWiki\IPInfo;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\IPInfo\Info\Asn;
use MediaWiki\IPInfo\Info\Coordinates;
use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\Info\Location;

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
	 * @param string $filename
	 * @return Reader|null null if the file path or file is invalid
	 */
	private function getReader( string $filename ) : ?Reader {
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
	 */
	public function retrieveFromIP( string $ip ) : Info {
		return new Info(
			'ipinfo-source-geoip2',
			$this->getCoordinates( $ip ),
			$this->getAsn( $ip ),
			$this->getLocations( $ip )
		);
	}

	/**
	 * @param string $ip
	 * @return Coordinates|null null if IP address does not return a latitude/longitude
	 */
	private function getCoordinates( string $ip ) : ?Coordinates {
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
	private function getAsn( string $ip ) : ?Asn {
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
			$asn->autonomousSystemNumber,
			$asn->autonomousSystemOrganization
		);
	}

	/**
	 * @param string $ip
	 * @return Location[] Empty array if IP address does not return a city name
	 */
	private function getLocations( string $ip ) : array {
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
			function ( $subdivision ) {
				return new Location(
					$subdivision->geonameId,
					$subdivision->name
				);
			},
			$city->subdivisions
		) );
	}
}
