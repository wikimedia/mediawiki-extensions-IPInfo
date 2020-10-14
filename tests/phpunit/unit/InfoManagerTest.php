<?php

namespace MediaWiki\IPInfo\Test\Unit;

use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\InfoRetriever;
use MediaWikiUnitTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoManager
 */
class InfoManagerTest extends MediaWikiUnitTestCase {

	public function testRetrieveFromIP() {
		$infoRetriever = $this->createMock( InfoRetriever::class );
		$infoRetriever->method( 'retrieveFromIP' )
			->willReturn( $this->createMock( Info::class ) );

		$infoManager = new InfoManager( [ $infoRetriever ] );
		$info = $infoManager->retrieveFromIP( '127.0.0.1' );

		$this->assertArrayHasKey( 'subject', $info );
		$this->assertArrayHasKey( 'data', $info );
		$this->assertInstanceOf( Info::class, $info['data'][0] );
	}

}
