<?php

namespace MediaWiki\IPInfo\Test\Unit\Info;

use MediaWiki\IPInfo\Info\IPoidInfo;
use MediaWiki\Json\FormatJson;
use MediaWikiUnitTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\Info\IPoidInfo
 */
class IPoidInfoTest extends MediaWikiUnitTestCase {
	public function testDefaultValues() {
		$info = new IPoidInfo();

		$this->assertNull( $info->getBehaviors() );
		$this->assertNull( $info->getRisks() );
		$this->assertNull( $info->getConnectionTypes() );
		$this->assertNull( $info->getTunnelOperators() );
		$this->assertNull( $info->getProxies() );
		$this->assertNull( $info->getNumUsersOnThisIP() );
	}

	public function testJsonSerialize() {
		$this->assertJsonStringEqualsJsonString(
			'{"behaviors":null,"risks":null,"connectionTypes":null,"tunnelOperators":null,' .
			'"proxies":null,"numUsersOnThisIP":null}',
			FormatJson::encode( new IPoidInfo() )
		);
	}
}
