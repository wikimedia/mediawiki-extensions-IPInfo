<?php

namespace MediaWiki\IPInfo\Test\Unit\Info;

use MediaWiki\IPInfo\Info\Coordinates;
use MediaWikiUnitTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\Info\Coordinates
 */
class CoordinatesTest extends MediaWikiUnitTestCase {
	public function testDefaultValues() {
		$info = new Coordinates( 1.0, 2.0 );

		$this->assertSame( 1.0, $info->getLatitude() );
		$this->assertSame( 2.0, $info->getLongitude() );
	}
}
