<?php

namespace MediaWiki\IPInfo\Test\Unit\Rest\Presenter;

use Generator;
use MediaWiki\IPInfo\Info\BlockInfo;
use MediaWiki\IPInfo\Info\ContributionInfo;
use MediaWiki\IPInfo\Info\Coordinates;
use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\Info\IPCountInfo;
use MediaWiki\IPInfo\Info\IPoidInfo;
use MediaWiki\IPInfo\Info\IPVersionInfo;
use MediaWiki\IPInfo\Info\Location;
use MediaWiki\IPInfo\Info\ProxyType;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use stdClass;
use Wikimedia\Assert\ParameterElementTypeException;
use Wikimedia\TestingAccessWrapper;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter
 */
class DefaultPresenterTest extends MediaWikiUnitTestCase {
	public static function providePresentBasic(): Generator {
		yield [ [], [] ];
		yield [
			[
				'foo' => [
					'countryNames' => null,
					'connectionType' => null,
					'userType' => null,
					'proxyType' => null,
				],
			],
			[
				'foo' => new Info(),
			]
		];
		yield [
			[
				'bar' => [
					'countryNames' => [ 'en' => 'Thailand' ],
					'connectionType' => 'quux',
					'userType' => 'residential',
					'proxyType' => array_fill_keys( [
						'isAnonymousVpn',
						'isPublicProxy',
						'isResidentialProxy',
						'isLegitimateProxy',
						'isTorExitNode',
						'isHostingProvider'
					], false ),
				],
			],
			[
				'bar' => new Info(
					new Coordinates( 51.509865, -0.118092 ),
					0,
					'baz',
					[ 'en' => 'Thailand' ],
					[
						new Location( 123456789, 'London' )
					],
					'qux',
					'quux',
					'residential',
					new ProxyType(
						false,
						false,
						false,
						false,
						false,
						false
					)
				),
			]
		];
		yield [
			[
				'quuz' => [
					'numLocalEdits' => 42,
					'numRecentEdits' => 24,
				]
			],
			[
				'quuz' => new ContributionInfo( 42, 24 )
			]
		];
		yield [
			[
				'quuz' => [
					'numActiveBlocks' => 1,
				]
			],
			[
				'quuz' => new BlockInfo( 1 ),
			]
		];
		yield [
			[
				'quuz' => []
				],
				[
					'quuz' => new IPoidInfo(),
				]

		];
		yield [
			[ 'quuz' => [] ],
			[ 'quuz' => new IPCountInfo( null ) ]
		];
		yield [
			[ 'quuz' => [] ],
			[ 'quuz' => new IPCountInfo( 0 ) ]
		];
		yield [
			[ 'quuz' => [] ],
			[ 'quuz' => new IPCountInfo( 2 ) ]
		];
		yield [
			[ 'quuz' => [ 'version' => 'ipv6' ] ],
			[ 'quuz' => new IPVersionInfo( 'ipv6' ) ]
		];
	}

	/**
	 * @dataProvider providePresentBasic
	 */
	public function testPresentBasic( $expectedData, $data ) {
		$info = [
			'subject' => '172.18.0.1',
			'data' => $data,
		];
		$expected = [
			'subject' => '172.18.0.1',
			'data' => $expectedData,
		];

		$user = new UserIdentityValue( 1, 'username' );
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'getUserPermissions' )
		  ->with( $user )
		  ->willReturn( [ DefaultPresenter::IPINFO_VIEW_BASIC_RIGHT ] );

		$this->assertArrayEquals(
			$expected,
			( new DefaultPresenter( $permissionManager ) )
				->present( $info, $user )
		);
	}

	public static function providePresentFull(): Generator {
		yield [
			[
				'foo' => [
					'organization' => null,
					'countryNames' => null,
					'location' => null,
					'asn' => null,
					'isp' => null,
					'connectionType' => null,
					'userType' => null,
					'proxyType' => null,
				],
			],
			[
				'foo' => new Info(),
			]
		];
		yield [
			[
				'bar' => [
					'organization' => 'baz',
					'countryNames' => [ 'en' => 'Thailand' ],
					'location' => [
						[
							'id' => 123456789,
							'label' => 'London'
						],
					],
					'asn' => 0,
					'isp' => 'qux',
					'connectionType' => 'quux',
					'userType' => 'residential',
					'proxyType' => array_fill_keys( [
						'isAnonymousVpn',
						'isPublicProxy',
						'isResidentialProxy',
						'isLegitimateProxy',
						'isTorExitNode',
						'isHostingProvider'
					], false ),
				],
			],
			[
				'bar' => new Info(
					new Coordinates( 51.509865, -0.118092 ),
					0,
					'baz',
					[ 'en' => 'Thailand' ],
					[
						new Location( 123456789, 'London' )
					],
					'qux',
					'quux',
					'residential',
					new ProxyType(
						false,
						false,
						false,
						false,
						false,
						false
					)
				),
			],
		];
		yield [
			[ 'quuz' => [ 'numIPAddresses' => null ] ],
			[ 'quuz' => new IPCountInfo( null ) ]
		];
		yield [
			[ 'quuz' => [ 'numIPAddresses' => 0 ] ],
			[ 'quuz' => new IPCountInfo( 0 ) ]
		];
		yield [
			[ 'quuz' => [ 'numIPAddresses' => 2 ] ],
			[ 'quuz' => new IPCountInfo( 2 ) ]
		];
		yield [
			[ 'quuz' => [ 'version' => 'ipv6' ] ],
			[ 'quuz' => new IPVersionInfo( 'ipv6' ) ]
		];
	}

	/**
	 * @dataProvider providePresentFull
	 */
	public function testPresentFull( $expectedData, $data ) {
		$info = [
			'subject' => '172.18.0.1',
			'data' => $data,
		];
		$expected = [
			'subject' => '172.18.0.1',
			'data' => $expectedData,
		];

		$user = new UserIdentityValue( 1, 'username' );
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'getUserPermissions' )
			->with( $user )
			->willReturn( [ DefaultPresenter::IPINFO_VIEW_FULL_RIGHT ] );

		$this->assertArrayEquals(
			$expected,
			( new DefaultPresenter( $permissionManager ) )
				->present( $info, $user )
		);
	}

	public static function providePresentUnknownDataType(): Generator {
		yield [ null ];
		yield [ [] ];
		yield [ new stdClass() ];
	}

	/**
	 * @dataProvider providePresentUnknownDataType
	 */
	public function testPresentUnknownDataType( $data ) {
		$this->expectException( ParameterElementTypeException::class );

		$user = new UserIdentityValue( 1, 'username' );
		$permissionManager = $this->createMock( PermissionManager::class );

		( new DefaultPresenter( $permissionManager ) )->present( [
			'subject' => '172.18.0.1',
			'data' => [ 'foo' => $data ],
		], $user );
	}

	public function testPresentBlockInfo() {
		$info = new BlockInfo( 1 );
		$permissionManager = $this->createMock( PermissionManager::class );
		$wrapper = TestingAccessWrapper::newFromObject( new DefaultPresenter( $permissionManager ) );

		$this->assertArrayEquals( [ 0 => 1 ], $wrapper->presentBlockInfo( $info ) );
	}

	public function testPresentIPoidInfo() {
		$info = new IPoidInfo( [], [ 'GEO_MISMATCH', 'CALLBACK_PROXY' ], [], [], [], 2 );
		$permissionManager = $this->createMock( PermissionManager::class );
		$wrapper = TestingAccessWrapper::newFromObject( new DefaultPresenter( $permissionManager ) );

		$this->assertArrayEquals( [
			'behaviors' => [],
			'risks' => [ 'GEO_MISMATCH', 'CALLBACK_PROXY' ],
			'connectionTypes' => [],
			'tunnelOperators' => [],
			'proxies' => [],
			'numUsersOnThisIP' => 2,
		], $wrapper->presentIPoidInfo( $info ) );
	}
}
