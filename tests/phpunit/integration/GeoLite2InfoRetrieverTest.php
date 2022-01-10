<?php

namespace MediaWiki\IPInfo\Test\Integration;

use LoggedServiceOptions;
use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\InfoRetriever\GeoLite2InfoRetriever;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use TestAllServiceOptionsUsed;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\GeoLite2InfoRetriever
 */
class GeoLite2InfoRetrieverTest extends MediaWikiIntegrationTestCase {
	use TestAllServiceOptionsUsed;

	public function testRetrieveFromIP() {
		$infoRetriever = new GeoLite2InfoRetriever(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				GeoLite2InfoRetriever::CONSTRUCTOR_OPTIONS,
				MediaWikiServices::getInstance()->getMainConfig()
			),
			MediaWikiServices::getInstance()->get( 'ReaderFactory' )
		);
		$info = $infoRetriever->retrieveFromIP( '127.0.0.1' );

		$this->assertInstanceOf( Info::class, $info );
	}

}
