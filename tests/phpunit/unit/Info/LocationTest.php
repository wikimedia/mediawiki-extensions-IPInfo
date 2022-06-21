<?php

namespace MediaWiki\IPInfo\Test\Unit\Info;

use MediaWiki\IPInfo\Info\Location;
use MediaWikiUnitTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\Info\Location
 */
class LocationTest extends MediaWikiUnitTestCase {
	public function testDefaultValues() {
		$info = new Location( 1, 'foo' );

		$this->assertSame( 1, $info->getId() );
		$this->assertSame( 'foo', $info->getLabel() );
	}
}
