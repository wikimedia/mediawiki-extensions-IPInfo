<?php

namespace MediaWiki\IPInfo\Test\Unit\Info;

use MediaWiki\IPInfo\Info\ProxyType;
use MediaWiki\Json\FormatJson;
use MediaWikiUnitTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\Info\ProxyType
 */
class ProxyTypeTest extends MediaWikiUnitTestCase {
	public function testDefaultValues() {
		$info = new ProxyType( true, true, true, true, true, true );

		$this->assertTrue( $info->isAnonymousVpn() );
		$this->assertTrue( $info->isPublicProxy() );
		$this->assertTrue( $info->isResidentialProxy() );
		$this->assertTrue( $info->isLegitimateProxy() );
		$this->assertTrue( $info->isTorExitNode() );
		$this->assertTrue( $info->isHostingProvider() );
	}

	public function testJsonSerialize() {
		$this->assertJsonStringEqualsJsonString(
			'{"isAnonymousVpn":true,"isResidentialProxy":true,"isLegitimateProxy":true,' .
			'"isTorExitNode":true,"isHostingProvider":true}',
			FormatJson::encode(
				new ProxyType( true, true, true, true, true, true )
			)
		);
	}
}
