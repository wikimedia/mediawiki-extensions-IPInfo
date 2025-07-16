<?php

namespace MediaWiki\IPInfo\Test\Unit\Info;

use MediaWiki\IPInfo\Info\Info;
use MediaWiki\Json\FormatJson;
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
		$this->assertNull( $info->getCountryNames() );
		$this->assertNull( $info->getLocation() );
		$this->assertNull( $info->getConnectionType() );
		$this->assertNull( $info->getProxyType() );
	}

	public function testJsonSerialize() {
		$this->assertJsonStringEqualsJsonString(
			'{"coordinates":null,"asn":null,"organization":null,"countryNames":null,' .
			'"location":null,"connectionType":null,"userType":null,"proxyType":null}',
			FormatJson::encode( new Info() )
		);
	}
}
