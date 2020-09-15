<?php

namespace MediaWiki\IPInfo\Test\Unit;

use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\InfoManager;
use MediaWikiUnitTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoManager
 */
class InfoManagerTest extends MediaWikiUnitTestCase {

	public function testRetrieveFromIP() {
		$infoManager = new InfoManager();
		$info = $infoManager->retrieveFromIP( '127.0.0.1' );

		$this->assertInstanceOf( Info::class, $info );
	}

}
