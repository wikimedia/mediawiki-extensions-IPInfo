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

	/**
	 * @dataProvider provideRetrieveBatch
	 * @param string[]|null $retrieverNames
	 * @param array $expectedData
	 */
	public function testRetrieveBatch(
		?array $retrieverNames,
		array $expectedData
	): void {
		$user = new UserIdentityValue( 1, '~2024-8' );
		$ips = [ '1.1.1.1', '2.2.2.2', '3.3.3.3' ];

		$retrieverSetup = [
			'first-retriever' => [
				'1.1.1.1' => null,
				'2.2.2.2' => 'some-data',
				'3.3.3.3' => 'other-data'
			],
			'second-retriever' => [
				'1.1.1.1' => 'second-retriever-data',
				'2.2.2.2' => null,
				'3.3.3.3' => 'more-second-retriever-data'
			],
			'third-retriever' => [
				'1.1.1.1' => 'third-retriever-data',
				'2.2.2.2' => 'more-third-retriever-data',
				'3.3.3.3' => 'other-third-retriever-data'
			],
		];

		$retrievers = [];

		foreach ( $retrieverSetup as $name => $returnValue ) {
			$retriever = $this->createMock( InfoRetriever::class );
			$retriever->method( 'getName' )
				->willReturn( $name );

			if ( $retrieverNames !== null && !in_array( $name, $retrieverNames ) ) {
				$retriever->expects( $this->never() )
					->method( 'retrieveBatch' );
				continue;
			}

			$retriever->method( 'retrieveBatch' )
				->with( $user, $ips )
				->willReturn( $returnValue );

			$retrievers[] = $retriever;
		}

		$infoManager = new InfoManager( $retrievers );

		$info = $infoManager->retrieveBatch( $user, $ips );

		$this->assertSame( $expectedData, $info );
	}

	public static function provideRetrieveBatch(): iterable {
		yield 'all supported retrievers included' => [
			null,
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
		];

		yield 'one retriever excluded' => [
			[ 'first-retriever', 'third-retriever' ],
			[
				'1.1.1.1' => [
					'subject' => '~2024-8',
					'data' => [
						'first-retriever' => null,
						'third-retriever' => 'third-retriever-data',
					]
				],
				'2.2.2.2' => [
					'subject' => '~2024-8',
					'data' => [
						'first-retriever' => 'some-data',
						'third-retriever' => 'more-third-retriever-data',
					]
				],
				'3.3.3.3' => [
					'subject' => '~2024-8',
					'data' => [
						'first-retriever' => 'other-data',
						'third-retriever' => 'other-third-retriever-data',
					]
				],
			],
		];
	}

}
