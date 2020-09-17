<?php

namespace MediaWiki\IPInfo\Test\Unit\Info;

use MediaWiki\IPInfo\Info\Info;
use MediaWikiUnitTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\Info\Info
 */
class InfoTest extends MediaWikiUnitTestCase {

	public function testJsonSerialize() {
		$expected = json_encode( [
			'coordinates' => null,
			'asn' => null,
			'location' => [],
		] );

		$info = new Info();

		$this->assertSame( $expected, json_encode( $info ) );
	}

}
