<?php
namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use MediaWiki\IPInfo\Info\IPVersionInfo;
use MediaWiki\IPInfo\InfoRetriever\IPVersionInfoRetriever;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\IPInfo\Info\IPVersionInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\IPVersionInfoRetriever
 */
class IPVersionInfoRetrieverTest extends MediaWikiUnitTestCase {
	/**
	 * @dataProvider provideIPVersion
	 */
	public function testShouldReturnIPVersion( ?string $ip, ?string $expected ): void {
		$retriever = new IPVersionInfoRetriever();

		$info = $retriever->retrieveFor(
			new UserIdentityValue( 1, '~2024-5' ),
			$ip
		);

		$this->assertInstanceOf( IPVersionInfo::class, $info );
		$this->assertSame( $expected, $info->getVersion() );
	}

	public static function provideIPVersion(): iterable {
		yield 'missing IP' => [ null, null ];
		yield 'IPv4' => [ '127.0.0.1', 'ipv4' ];
		yield 'IPv6' => [ '::1', 'ipv6' ];
	}
}
