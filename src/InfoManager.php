<?php

namespace MediaWiki\IPInfo;

use MediaWiki\IPInfo\Info\ASN;
use MediaWiki\IPInfo\Info\Coordinates;
use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\Info\Location;

class InfoManager {

	/**
	 * Retrieve info about an IP address.
	 *
	 * @param string $ip
	 * @return Info
	 */
	public function retrieveFromIP( string $ip ) : Info {
		// @TODO Remove mock data.
		return new Info(
			new Coordinates( 38.897957, -77.036560 ),
			new ASN( 33363 ),
			[
				// @TODO Use Wikidata instead of GeoNames ID?
				new Location( 4140963, 'Washington' ),
				new Location( 4138106, 'District of Columbia' ),
				new Location( 6252001, 'United States' ),
			]
		);
	}
}
