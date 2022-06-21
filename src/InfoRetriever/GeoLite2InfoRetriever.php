<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\IPInfo\Info\Coordinates;
use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\Info\Location;

/**
 * Manager for getting information from the MaxMind GeoLite2 databases.
 */
class GeoLite2InfoRetriever implements InfoRetriever {
	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'IPInfoGeoLite2Prefix',
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
	protected function getReader( string $filename ): ?Reader {
		$path = $this->options->get( 'IPInfoGeoLite2Prefix' );
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
		return new Info(
			$this->getCoordinates( $ip ),
			$this->getAsn( $ip ),
			$this->getOrganization( $ip ),
			$this->getCountry( $ip ),
			$this->getLocations( $ip ),
			$this->getIsp( $ip ),
			$this->getConnectionType( $ip ),
			$this->getUserType( $ip ),
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
	 * @return int|null null if this IP address is not in the database
	 */
	private function getAsn( string $ip ): ?int {
		$reader = $this->getReader( 'ASN.mmdb' );
		if ( !$reader ) {
			return null;
		}

		try {
			return $reader->asn( $ip )->autonomousSystemNumber;
		} catch ( AddressNotFoundException $e ) {
			return null;
		}
	}

	/**
	 * @param string $ip
	 * @return string|null null if this IP address is not in the database
	 */
	private function getOrganization( string $ip ): ?string {
		$reader = $this->getReader( 'ASN.mmdb' );
		if ( !$reader ) {
			return null;
		}

		try {
			return $reader->asn( $ip )->autonomousSystemOrganization;
		} catch ( AddressNotFoundException $e ) {
			return null;
		}
	}

	/**
	 * @param string $ip
	 * @return Location[]|null null if this IP address is not in the database
	 */
	private function getCountry( string $ip ): ?array {
		$reader = $this->getReader( 'City.mmdb' );
		if ( !$reader ) {
			return null;
		}

		try {
			$city = $reader->city( $ip );
		} catch ( AddressNotFoundException $e ) {
			return null;
		}

		if ( !$city->country->geonameId || !$city->country->name ) {
			return null;
		}

		return [ new Location(
			$city->country->geonameId,
			$city->country->name
		) ];
	}

	/**
	 * @param string $ip
	 * @return Location[]|null null if this IP address is not in the database
	 */
	private function getLocations( string $ip ): ?array {
		$reader = $this->getReader( 'City.mmdb' );
		if ( !$reader ) {
			return null;
		}

		try {
			$city = $reader->city( $ip );
		} catch ( AddressNotFoundException $e ) {
			return null;
		}

		if ( !$city->city->geonameId || !$city->city->name ) {
			return null;
		}

		$locations = [ new Location(
			$city->city->geonameId,
			$city->city->name
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
			array_reverse( $city->subdivisions )
		) );
	}

	/**
	 * ISP not available with GeoLite2
	 * See https://www.maxmind.com/en/solutions/geoip2-enterprise-product-suite/enterprise-database
	 * @param string $ip
	 * @return null
	 * @codeCoverageIgnore tested when retrieveFromIP is run
	 */
	private function getIsp( string $ip ) {
		return null;
	}

	/**
	 * Connection type not available with GeoLite2
	 * See https://www.maxmind.com/en/solutions/geoip2-enterprise-product-suite/enterprise-database
	 * @param string $ip
	 * @return null
	 */
	private function getConnectionType( string $ip ) {
		return null;
	}

	/**
	 * User type not available with GeoLite2
	 * See https://www.maxmind.com/en/solutions/geoip2-enterprise-product-suite/enterprise-database
	 * @param string $ip
	 * @return null
	 */
	private function getUserType( string $ip ) {
		return null;
	}

	/**
	 * Proxy type not available with GeoLite2
	 * See https://www.maxmind.com/en/solutions/geoip2-enterprise-product-suite/enterprise-database
	 * @param string $ip
	 * @return null
	 */
	private function getProxyType( string $ip ) {
		return null;
	}
}
