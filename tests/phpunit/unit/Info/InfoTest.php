<?php

namespace MediaWiki\IPInfo\Test\Unit\Info;

use MediaWiki\IPInfo\Info\Info;
use MediaWikiUnitTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\Info\Info
 */
class InfoTest extends MediaWikiUnitTestCase {
	public function testDefaultValues() {
		$info = new Info();

		$this->assertNull( $info->getCoordinates() );
		$this->assertNull( $info->getAsn() );
		$this->assertNull( $info->getOrganization() );
		$this->assertNull( $info->getCountry() );
		$this->assertNull( $info->getLocation() );
		$this->assertNull( $info->getIsp() );
		$this->assertNull( $info->getConnectionType() );
		$this->assertNull( $info->getProxyType() );
	}
}
