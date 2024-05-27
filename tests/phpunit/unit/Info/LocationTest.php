<?php

namespace MediaWiki\IPInfo\Test\Unit\Info;

use MediaWiki\IPInfo\Info\Location;
use MediaWiki\Json\FormatJson;
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

	public function testJsonSerialize() {
		$this->assertJsonStringEqualsJsonString(
			'{"id":1,"label":"foo"}',
			FormatJson::encode( new Location( 1, 'foo' ) )
		);
	}
}
