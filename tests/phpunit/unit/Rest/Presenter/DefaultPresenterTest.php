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
use MediaWikiUnitTestCase;
use stdClass;
use Wikimedia\Assert\ParameterElementTypeException;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter
 */
class DefaultPresenterTest extends MediaWikiUnitTestCase {
	public function providePresent(): Generator {
		yield [ [], [] ];
		yield [
			[
				[
					'source' => 'foo',
					'coordinates' => null,
					'asn' => null,
					'organization' => null,
					'location' => [],
					'isp' => null,
					'connectionType' => null,
					'proxyType' => null,
				],
			],
			[
				'foo' => new Info(),
			]
		];
		yield [
			[
				[
					'source' => 'bar',
					'coordinates' => [
						'latitude' => 51.509865,
						'longitude' => -0.118092,
					],
					'asn' => 0,
					'organization' => 'baz',
					'location' => [
						[
							'id' => 123456789,
							'label' => 'London'
						],
					],
					'isp' => 'qux',
					'connectionType' => 'quux',
					'proxyType' => array_fill_keys( [
						'isAnonymous',
						'isAnonymousVpn',
						'isPublicProxy',
						'isResidentialProxy',
						'isLegitimateProxy',
						'isTorExitNode'
					], false ),
				],
			],
			[
				'bar' => new Info(
					new Coordinates( 51.509865, -0.118092 ),
					0,
					'baz',
					[
						new Location( 123456789, 'London' )
					],
					'qux',
					'quux',
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
			[
				[
					'source' => 'quuz',
					'numActiveBlocks' => 1,
				],
				[
					'quuz' => new BlockInfo( 1 ),
				]
			]
		];
		yield [
			[
				[
					'source' => 'quuz',
					'numLocalEdits' => 42,
					'numRecentEdits' => 24,
				]
			],
			[
				'quuz' => new ContributionInfo( 42, 24 )
			]
		];
	}

	/**
	 * @dataProvider providePresent
	 */
	public function testPresent( $expectedData, $data ) {
		$info = [
			'subject' => '172.18.0.1',
			'data' => $data,
		];
		$expected = [
			'subject' => '172.18.0.1',
			'data' => $expectedData,
		];

		$this->assertArrayEquals(
			$expected,
			( new DefaultPresenter() )->present( $info )
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

		( new DefaultPresenter() )->present( [
			'subject' => '172.18.0.1',
			'data' => [ 'foo' => $data ],
		] );
	}
}
