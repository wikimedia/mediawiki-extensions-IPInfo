<?php

namespace MediaWiki\IPInfo\Test\Integration;

use LoggedServiceOptions;
use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\InfoRetriever\GeoIp2InfoRetriever;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use TestAllServiceOptionsUsed;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\GeoIp2InfoRetriever
 */
class GeoIp2InfoRetrieverTest extends MediaWikiIntegrationTestCase {
	use TestAllServiceOptionsUsed;

	public function testRetrieveFromIP() {
		$infoRetriever = new GeoIp2InfoRetriever(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				GeoIp2InfoRetriever::CONSTRUCTOR_OPTIONS,
				MediaWikiServices::getInstance()->getMainConfig()
			)
		);
		$info = $infoRetriever->retrieveFromIP( '127.0.0.1' );

		$this->assertInstanceOf( Info::class, $info );
	}

}
