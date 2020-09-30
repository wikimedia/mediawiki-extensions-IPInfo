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
			'subject' => '127.0.0.1',
			'coordinates' => null,
			'asn' => null,
			'location' => [],
		] );

		$info = new Info( '127.0.0.1' );

		$this->assertSame( $expected, json_encode( $info ) );
	}

}
