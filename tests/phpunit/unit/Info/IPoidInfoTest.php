<?php

namespace MediaWiki\IPInfo\Test\Unit\Info;

use MediaWiki\IPInfo\Info\IPoidInfo;
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
}
