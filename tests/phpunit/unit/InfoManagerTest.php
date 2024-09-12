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

		$ip = $user;
		if ( $user instanceof UserIdentity ) {
			$ip = $user->isRegistered() ? '127.0.0.1' : $user->getName();
		}

		$infoManager = new InfoManager( [ $infoRetriever ] );
		$info = $infoManager->retrieveFor( $user, $ip );

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

	public function testRetrieveBatch(): void {
		$user = new UserIdentityValue( 1, '~2024-8' );
		$ips = [ '1.1.1.1', '2.2.2.2', '3.3.3.3' ];

		$firstRetriever = $this->createMock( InfoRetriever::class );
		$firstRetriever->method( 'retrieveBatch' )
			->with( $user, $ips )
			->willReturn( [
				'1.1.1.1' => null,
				'2.2.2.2' => 'some-data',
				'3.3.3.3' => 'other-data'
			] );
		$firstRetriever->method( 'getName' )
			->willReturn( 'first-retriever' );

		$secondRetriever = $this->createMock( InfoRetriever::class );
		$secondRetriever->method( 'retrieveBatch' )
			->with( $user, $ips )
			->willReturn( [
				'1.1.1.1' => 'second-retriever-data',
				'2.2.2.2' => null,
				'3.3.3.3' => 'more-second-retriever-data'
			] );
		$secondRetriever->method( 'getName' )
			->willReturn( 'second-retriever' );

		$thirdRetriever = $this->createMock( InfoRetriever::class );
		$thirdRetriever->method( 'retrieveBatch' )
			->with( $user, $ips )
			->willReturn( [
				'1.1.1.1' => 'third-retriever-data',
				'2.2.2.2' => 'more-third-retriever-data',
				'3.3.3.3' => 'other-third-retriever-data'
			] );

		$thirdRetriever->method( 'getName' )
			->willReturn( 'third-retriever' );

		$infoManager = new InfoManager( [
			$firstRetriever,
			$secondRetriever,
			$thirdRetriever
		] );

		$info = $infoManager->retrieveBatch( $user, $ips );

		$this->assertSame(
			[
				'1.1.1.1' => [
					'subject' => '~2024-8',
					'data' => [
						'first-retriever' => null,
						'second-retriever' => 'second-retriever-data',
						'third-retriever' => 'third-retriever-data',
					]
				],
				'2.2.2.2' => [
					'subject' => '~2024-8',
					'data' => [
						'first-retriever' => 'some-data',
						'second-retriever' => null,
						'third-retriever' => 'more-third-retriever-data',
					]
				],
				'3.3.3.3' => [
					'subject' => '~2024-8',
					'data' => [
						'first-retriever' => 'other-data',
						'second-retriever' => 'more-second-retriever-data',
						'third-retriever' => 'other-third-retriever-data',
					]
				],
			],
			$info
		);
	}

}
