<?php

namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\AnonymousIp;
use GeoIp2\Model\Enterprise;
use LoggedServiceOptions;
use MediaWiki\IPInfo\Info\Coordinates;
use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\Info\Location;
use MediaWiki\IPInfo\Info\ProxyType;
use MediaWiki\IPInfo\InfoRetriever\GeoIp2EnterpriseInfoRetriever;
use MediaWiki\IPInfo\InfoRetriever\ReaderFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use TestAllServiceOptionsUsed;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\GeoIp2EnterpriseInfoRetriever
 */
class GeoIp2EnterpriseInfoRetrieverTest extends MediaWikiUnitTestCase {
	use TestAllServiceOptionsUsed;

	private ReaderFactory $readerFactory;

	protected function setUp(): void {
		parent::setUp();

		$this->readerFactory = $this->createMock( ReaderFactory::class );
	}

	private function getInfoRetriever( array $configOverrides = [] ): GeoIp2EnterpriseInfoRetriever {
		return new GeoIp2EnterpriseInfoRetriever(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				GeoIp2EnterpriseInfoRetriever::CONSTRUCTOR_OPTIONS,
				$configOverrides + [ 'IPInfoGeoIP2EnterprisePath' => 'test' ]
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

	public function testNoGeoIP2EnterprisePath() {
		$user = new UserIdentityValue( 0, '127.0.0.1' );

		$this->readerFactory->expects( $this->never() )
			->method( 'get' );

		$retrieverWithoutPrefix = $this->getInfoRetriever( [ 'IPInfoGeoIP2EnterprisePath' => false ] );
		$info = $retrieverWithoutPrefix->retrieveFor( $user, '127.0.0.1' );

		$this->assertEmptyInfo( $info );
	}

	public function testNullRetrieveFor() {
		$user = new UserIdentityValue( 0, '127.0.0.1' );

		$reader = $this->createMock( Reader::class );
		$reader->method( 'enterprise' )
			->willThrowException(
				new AddressNotFoundException()
			);
		$reader->method( 'anonymousIp' )
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

	public function testRetrieveForTemporaryUserWithMissingIPData() {
		$user = new UserIdentityValue( 4, '~2024-8' );

		$this->readerFactory->expects( $this->never() )
			->method( 'get' );

		$retriever = $this->getInfoRetriever();
		$info = $retriever->retrieveFor( $user, null );

		$this->assertInstanceOf( Info::class, $info );
		$this->assertSame( 'ipinfo-source-geoip2', $retriever->getName() );
		$this->assertEmptyInfo( $info );
	}

	/**
	 * @dataProvider provideUsers
	 */
	public function testRetrieveFor( UserIdentity $user, string $ip ) {
		$location = [
			'latitude' => 1,
			'longitude' => 2,
		];

		$country = [
			'geoname_id' => 1,
			'name' => 'bar',
			'names' => [ 'en' => 'bar' ],
		];

		$traits = [
			'autonomous_system_number' => 123,
			'autonomous_system_organization' => 'foobar',
			'is_legitimate_proxy' => true,
		];

		$enterprise = $this->getMockBuilder( Enterprise::class )
			->setConstructorArgs( [ [
				'location' => $location,
				'country' => $country,
				'city' => $country,
				'traits' => $traits,
			] ] )
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->getMock();

		$anonymousIp = $this->getMockBuilder( AnonymousIp::class )
			->setConstructorArgs( [ [
				'is_anonymous_vpn' => true,
				'is_public_proxy' => true,
				'is_residential_proxy' => true,
				'is_tor_exit_node' => true,
				'is_hosting_provider' => true,
				'ip_address' => '123.123.123.123',
				'prefix_len' => 24,
			] ] )
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->getMock();

		$reader = $this->createMock( Reader::class );
		$reader->method( 'enterprise' )
			->with( $ip )
			->willReturn( $enterprise );
		$reader->method( 'anonymousIp' )
			->with( $ip )
			->willReturn( $anonymousIp );

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
		$this->assertEquals( new ProxyType( true, true, true, true, true, true ), $info->getProxyType() );
	}

	public static function provideUsers(): iterable {
		yield 'anonymous user' => [ new UserIdentityValue( 0, '127.0.0.1' ), '127.0.0.1' ];
		yield 'temporary user' => [ new UserIdentityValue( 4, '~2024-8' ), '127.0.0.1' ];
	}

}
