<?php

namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\Asn;
use GeoIp2\Model\City;
use LoggedServiceOptions;
use MediaWiki\IPInfo\Info\Coordinates;
use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\Info\Location;
use MediaWiki\IPInfo\InfoRetriever\GeoLite2InfoRetriever;
use MediaWiki\IPInfo\InfoRetriever\ReaderFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use TestAllServiceOptionsUsed;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\GeoLite2InfoRetriever
 */
class GeoLite2InfoRetrieverTest extends MediaWikiUnitTestCase {
	use TestAllServiceOptionsUsed;

	private ReaderFactory $readerFactory;

	protected function setUp(): void {
		parent::setUp();

		$this->readerFactory = $this->createMock( ReaderFactory::class );
	}

	private function getInfoRetriever( array $configOverrides = [] ): GeoLite2InfoRetriever {
		return new GeoLite2InfoRetriever(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				GeoLite2InfoRetriever::CONSTRUCTOR_OPTIONS,
				$configOverrides + [ 'IPInfoGeoLite2Prefix' => 'test' ]
			),
			$this->readerFactory
		);
	}

	/**
	 * Convenience function to assert that the given Info holds no data.
	 */
	private function assertEmptyInfo( Info $info ): void {
		$this->assertNull( $info->getCoordinates() );
		$this->assertNull( $info->getAsn() );
		$this->assertNull( $info->getOrganization() );
		$this->assertNull( $info->getCountryNames() );
		$this->assertNull( $info->getLocation() );
		$this->assertNull( $info->getConnectionType() );
		$this->assertNull( $info->getUserType() );
		$this->assertNull( $info->getProxyType() );
	}

	public function testNoGeoLite2Prefix() {
		$user = new UserIdentityValue( 0, '127.0.0.1' );

		$reader = $this->createMock( Reader::class );

		$this->readerFactory->method( 'get' )
			->willReturn( $reader );
		$this->readerFactory->expects( $this->never() )
			->method( 'get' );

		$retrieverWithoutPrefix = $this->getInfoRetriever( [ 'IPInfoGeoLite2Prefix' => false ] );
		$info = $retrieverWithoutPrefix->retrieveFor( $user, '127.0.0.1' );

		$this->assertEmptyInfo( $info );
	}

	public function testNullRetrieveFor() {
		$user = new UserIdentityValue( 0, '127.0.0.1' );

		$reader = $this->createMock( Reader::class );
		$reader->method( 'asn' )
			->willThrowException(
				new AddressNotFoundException()
			);
		$reader->method( 'city' )
			->willThrowException(
				new AddressNotFoundException()
			);

		$this->readerFactory->method( 'get' )
			->willReturn( $reader );
		$this->readerFactory->expects( $this->atLeastOnce() )
			->method( 'get' );

		$retriever = $this->getInfoRetriever();
		$info = $retriever->retrieveFor( $user, '127.0.0.1' );

		$this->assertInstanceOf( Info::class, $info );
		$this->assertSame( 'ipinfo-source-geoip2', $retriever->getName() );
		$this->assertEmptyInfo( $info );
	}

	/**
	 * @dataProvider provideUsers
	 */
	public function testRetrieveFor( UserIdentity $user ) {
		$ip = '127.0.0.1';

		$location = [
			'latitude' => 1,
			'longitude' => 2,
		];

		$country = [
			'geoname_id' => 1,
			'name' => 'bar',
			'names' => [ 'en' => 'bar' ],
		];

		$city = $this->getMockBuilder( City::class )
			->setConstructorArgs( [ [
				'location' => $location,
				'country' => $country,
				'city' => $country,
			] ] )
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->getMock();

		$asn = $this->getMockBuilder( ASN::class )
			->setConstructorArgs( [ [
				'autonomous_system_number' => 123,
				'autonomous_system_organization' => 'foobar',
				'ip_address' => '123.123.123.123',
				'prefix_len' => 24,
			] ] )
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->getMock();

		$reader = $this->createMock( Reader::class );
		$reader->method( 'asn' )
			->with( $ip )
			->willReturn( $asn );
		$reader->method( 'city' )
			->with( $ip )
			->willReturn( $city );

		$this->readerFactory->method( 'get' )
			->willReturn( $reader );

		$info = $this->getInfoRetriever()->retrieveFor( $user, $ip );

		$this->assertEquals( new Coordinates( 1.0, 2.0 ), $info->getCoordinates() );
		$this->assertSame( 123, $info->getAsn() );
		$this->assertEquals( 'foobar', $info->getOrganization() );
		$this->assertEquals( [ 'en' => 'bar' ], $info->getCountryNames() );
		$this->assertEquals( [ new Location( 1, 'bar' ) ], $info->getLocation() );
		$this->assertNull( $info->getConnectionType() );
		$this->assertNull( $info->getUserType() );
		$this->assertNull( $info->getProxyType() );
	}

	public static function provideUsers(): iterable {
		yield 'anonymous user' => [ new UserIdentityValue( 0, '127.0.0.1' ) ];
		yield 'temporary user' => [ new UserIdentityValue( 4, '~2024-8' ) ];
	}

	public function testRetrieverForTemporaryUserWithMissingIPData() {
		$user = new UserIdentityValue( 4, '~2024-8' );

		$this->readerFactory->expects( $this->never() )
			->method( 'get' );

		$info = $this->getInfoRetriever()->retrieveFor( $user, null );

		$this->assertEmptyInfo( $info );
	}

}
