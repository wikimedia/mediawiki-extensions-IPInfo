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
			'source' => 'ipinfo-source-testsource',
			'coordinates' => null,
			'asn' => null,
			'location' => [],
			'isp' => null,
			'proxyType' => null,
		] );

		$info = new Info( 'ipinfo-source-testsource' );

		$this->assertSame( $expected, json_encode( $info ) );
	}

}
