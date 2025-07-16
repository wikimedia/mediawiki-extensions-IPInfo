<?php

namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\AnonymousIp;
use GeoIp2\Model\Enterprise;
use GeoIp2\Record\Country;
use GeoIp2\Record\Location as LocationRecord;
use GeoIp2\Record\Traits;
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
		$location = $this->createMock( LocationRecord::class );
		$country = $this->createMock( Country::class );
		$country->method( '__get' )
			->willReturnMap( [
				[ 'geonameId', 1 ],
				[ 'name', 'bar' ],
				[ 'names', [ 'en' => 'bar' ] ]
			] );
		$location->method( '__get' )
			->willReturnMap( [
				[ 'latitude', 1 ],
				[ 'longitude', 2 ]
			] );
		$traits = $this->createMock( Traits::class );
		$traits->method( '__get' )
			->willReturnMap( [
				[ 'autonomousSystemNumber', 123 ],
				[ 'autonomousSystemOrganization', 'foobar' ],
				[ 'isLegitimateProxy', true ]
			] );

		$enterprise = $this->createMock( Enterprise::class );
		$enterprise->method( '__get' )
			->willReturnMap( [
				[ 'location', $location ],
				[ 'country', $country ],
				[ 'city', $country ],
				[ 'subdivisions', [] ],
				[ 'traits', $traits ]
			] );
		$anonymousIp = $this->createMock( AnonymousIp::class );
		$anonymousIp->method( '__get' )
			->willReturnMap( [
				[ 'isAnonymousVpn', true ],
				[ 'isPublicProxy', true ],
				[ 'isResidentialProxy', true ],
				[ 'isTorExitNode', true ],
				[ 'isHostingProvider', true ]
			] );

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
