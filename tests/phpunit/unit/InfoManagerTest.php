<?php

namespace MediaWiki\IPInfo\Test\Unit;

use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\InfoRetriever\InfoRetriever;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoManager
 */
class InfoManagerTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideRetrieveFor
	 * @param UserIdentity|string $user
	 * @param string $expectedSubject
	 */
	public function testRetrieveFromIP( $user, $expectedSubject ) {
		$retrieverName = 'foo';

		$infoRetriever = $this->createMock( InfoRetriever::class );
		$infoRetriever->method( 'retrieveFor' )
			->willReturn( $this->createMock( Info::class ) );
		$infoRetriever->method( 'getName' )
			->willReturn( $retrieverName );

		$infoManager = new InfoManager( [ $infoRetriever ] );
		$info = $infoManager->retrieveFor( $user );

		$this->assertSame( $expectedSubject, $info['subject'] );
		$this->assertArrayHasKey( 'data', $info );
		$this->assertInstanceOf(
			Info::class,
			$info['data'][$retrieverName],
			'Retrieved information is mapped to the name of the retriever'
		);
	}

	public static function provideRetrieveFor(): iterable {
		yield 'anonymous user, IPv4 string' => [
			'127.0.0.1',
			'127.0.0.1'
		];

		yield 'anonymous user, IPv6 string ' => [
			'2001:db8::8A2E:370:7334',
			'2001:db8::8a2e:370:7334'
		];

		yield 'anonymous user, IPv4' => [
			new UserIdentityValue( 0, '127.0.0.1' ),
			'127.0.0.1'
		];

		yield 'anonymous user, IPv6' => [
			new UserIdentityValue( 0, '2001:db8::8A2E:370:7334' ),
			'2001:db8::8a2e:370:7334'
		];

		yield 'temporary user' => [
			new UserIdentityValue( 4, '~2024-8' ),
			'~2024-8'
		];
	}

}
