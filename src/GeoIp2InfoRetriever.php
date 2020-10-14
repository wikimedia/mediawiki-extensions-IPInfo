<?php

namespace MediaWiki\IPInfo;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\IPInfo\Info\ASN;
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

	/** @var Reader|bool|null false if the file path or file is invalid */
	private $asnReader;

	/** @var Reader|bool|null false if the file path or file is invalid */
	private $cityReader;

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
	 * @return Reader|null null if the file path or file is invalid
	 */
	private function getAsnReader() : ?Reader {
		if ( $this->asnReader === null ) {
			$reader = $this->getReader( 'ASN.mmdb' );
			$this->asnReader = $reader ?: false;
		}

		return $this->asnReader ?: null;
	}

	/**
	 * @return Reader|null null if the file path or file is invalid
	 */
	private function getCityReader() : ?Reader {
		if ( $this->cityReader === null ) {
			$reader = $this->getReader( 'City.mmdb' );
			$this->cityReader = $reader ?: false;
		}

		return $this->cityReader ?: null;
	}

	/**
	 * @param string $filename
	 * @return Reader|null null if the file path or file is invalid
	 */
	private function getReader( string $filename ) : ?Reader {
		$path = $this->options->get( 'IPInfoGeoIP2Path' );

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
	 * @return Coordinates|null null if this IP address is not in the database
	 */
	private function getCoordinates( string $ip ) : ?Coordinates {
		$reader = $this->getCityReader();
		if ( !$reader ) {
			return null;
		}

		try {
			$city = $reader->city( $ip );
		} catch ( AddressNotFoundException $e ) {
			return null;
		}

		return new Coordinates(
			$city->location->latitude,
			$city->location->longitude
		);
	}

	/**
	 * @param string $ip
	 * @return Asn|null null if this IP address is not in the database
	 */
	private function getAsn( string $ip ) : ?ASN {
		$reader = $this->getAsnReader();
		if ( !$reader ) {
			return null;
		}

		try {
			$asn = $reader->asn( $ip );
		} catch ( AddressNotFoundException $e ) {
			return null;
		}

		return new ASN(
			$asn->autonomousSystemNumber,
			$asn->autonomousSystemOrganization
		);
	}

	/**
	 * @param string $ip
	 * @return Location[] Empty array if this IP address is not in the database
	 */
	private function getLocations( string $ip ) : array {
		$reader = $this->getCityReader();
		if ( !$reader ) {
			return [];
		}

		try {
			$city = $reader->city( $ip );
		} catch ( AddressNotFoundException $e ) {
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
