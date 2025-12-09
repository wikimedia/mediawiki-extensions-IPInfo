<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\IPInfo\Info\Coordinates;
use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\Info\Location;
use MediaWiki\User\UserIdentity;

/**
 * Manager for getting information from the MaxMind GeoLite2 databases.
 *
 * NOTE: Connection type, User type, and Proxy type are not available with GeoLite2
 * https://www.maxmind.com/en/solutions/geoip2-enterprise-product-suite/enterprise-database
 */
class GeoLite2InfoRetriever extends BaseInfoRetriever {
	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'IPInfoGeoLite2Prefix',
	];
	public const NAME = 'ipinfo-source-geoip2';

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly ReaderFactory $readerFactory,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
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
	public function retrieveFor( UserIdentity $user, ?string $ip ): Info {
		if ( $ip === null ) {
			return new Info();
		}

		return new Info(
			$this->getCoordinates( $ip ),
			$this->getAsn( $ip ),
			$this->getOrganization( $ip ),
			$this->getCountryNames( $ip ),
			$this->getLocations( $ip )
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
		} catch ( AddressNotFoundException ) {
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
		} catch ( AddressNotFoundException ) {
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
		} catch ( AddressNotFoundException ) {
			return null;
		}
	}

	private function getCountryNames( string $ip ): ?array {
		$reader = $this->getReader( 'City.mmdb' );
		if ( !$reader ) {
			return null;
		}

		try {
			$city = $reader->city( $ip );
		} catch ( AddressNotFoundException ) {
			return null;
		}

		return $city->country->names;
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
		} catch ( AddressNotFoundException ) {
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
}
