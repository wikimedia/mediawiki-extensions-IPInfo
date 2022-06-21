<?php

namespace MediaWiki\IPInfo\Test\Unit\Rest\Presenter;

use Generator;
use MediaWiki\IPInfo\Info\BlockInfo;
use MediaWiki\IPInfo\Info\ContributionInfo;
use MediaWiki\IPInfo\Info\Coordinates;
use MediaWiki\IPInfo\Info\Info;
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
	public function providePresentBasic(): Generator {
		yield [ [], [] ];
		yield [
			[
				'foo' => [
					'country' => null,
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
					'country' => [
						[
							'id' => 1605651,
							'label' => 'Thailand'
						],
					],
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
					[
						new Location( 1605651, 'Thailand' )
					],
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
		  ->with( $this->equalTo( $user ) )
		  ->willReturn( [ 'ipinfo-view-basic' ] );

		$this->assertArrayEquals(
			$expected,
			( new DefaultPresenter( $permissionManager ) )
				->present( $info, $user )
		);
	}

	public function providePresentFull(): Generator {
		yield [
			[
				'foo' => [
					'organization' => null,
					'country' => null,
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
					'country' => [
						[
							'id' => 1605651,
							'label' => 'Thailand'
						],
					],
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
					[
						new Location( 1605651, 'Thailand' )
					],
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
		  ->with( $this->equalTo( $user ) )
		  ->willReturn( [ 'ipinfo-view-full' ] );

		$this->assertArrayEquals(
			$expected,
			( new DefaultPresenter( $permissionManager ) )
				->present( $info, $user )
		);
	}

	public function providePresentUnknownDataType(): Generator {
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
}
