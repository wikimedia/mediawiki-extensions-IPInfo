<?php

namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\Asn;
use GeoIp2\Model\City;
use GeoIp2\Record\Country;
use GeoIp2\Record\Location as LocationRecord;
use LoggedServiceOptions;
use MediaWiki\IPInfo\Info\Coordinates;
use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\Info\Location;
use MediaWiki\IPInfo\InfoRetriever\GeoLite2InfoRetriever;
use MediaWiki\IPInfo\InfoRetriever\ReaderFactory;
use MediaWiki\IPInfo\TempUserIPLookup;
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

	private TempUserIPLookup $tempUserIPLookup;

	protected function setUp(): void {
		parent::setUp();

		$this->readerFactory = $this->createMock( ReaderFactory::class );
		$this->tempUserIPLookup = $this->createMock( TempUserIPLookup::class );
	}

	private function getInfoRetriever( array $configOverrides = [] ): GeoLite2InfoRetriever {
		return new GeoLite2InfoRetriever(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				GeoLite2InfoRetriever::CONSTRUCTOR_OPTIONS,
				$configOverrides + [ 'IPInfoGeoLite2Prefix' => 'test' ]
			),
			$this->readerFactory,
			$this->tempUserIPLookup
		);
	}

	/**
	 * Convenience function to assert that the given Info holds no data.
	 * @param Info $info
	 * @return void
	 */
	private function assertEmptyInfo( Info $info ): void {
		$this->assertNull( $info->getCoordinates() );
		$this->assertNull( $info->getAsn() );
		$this->assertNull( $info->getOrganization() );
		$this->assertNull( $info->getCountryNames() );
		$this->assertNull( $info->getLocation() );
		$this->assertNull( $info->getIsp() );
		$this->assertNull( $info->getConnectionType() );
		$this->assertNull( $info->getUserType() );
		$this->assertNull( $info->getProxyType() );
	}

	public function testNoGeoLite2Prefix() {
		$user = new UserIdentityValue( 0, '127.0.0.1' );

		$this->tempUserIPLookup->method( 'getMostRecentAddress' )
			->with( $user )
			->willReturn( '127.0.0.1' );

		$reader = $this->createMock( Reader::class );

		$this->readerFactory->method( 'get' )
			->willReturn( $reader );
		$this->readerFactory->expects( $this->never() )
			->method( 'get' );

		$retrieverWithoutPrefix = $this->getInfoRetriever( [ 'IPInfoGeoLite2Prefix' => false ] );
		$info = $retrieverWithoutPrefix->retrieveFor( $user );

		$this->assertEmptyInfo( $info );
	}

	public function testNullRetrieveFor() {
		$user = new UserIdentityValue( 0, '127.0.0.1' );

		$this->tempUserIPLookup->method( 'getMostRecentAddress' )
			->with( $user )
			->willReturn( '127.0.0.1' );

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
		$info = $retriever->retrieveFor( $user );

		$this->assertInstanceOf( Info::class, $info );
		$this->assertSame( 'ipinfo-source-geoip2', $retriever->getName() );
		$this->assertEmptyInfo( $info );
	}

	/**
	 * @dataProvider provideUsers
	 */
	public function testRetrieveFor( UserIdentity $user ) {
		$ip = '127.0.0.1';

		$this->tempUserIPLookup->method( 'getMostRecentAddress' )
			->with( $user )
			->willReturn( $ip );

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
		$city = $this->createMock( City::class );
		$city->method( '__get' )
			->willReturnMap( [
				[ 'location', $location ],
				[ 'country', $country ],
				[ 'city', $country ],
				[ 'subdivisions', [] ]
			] );

		$asn = $this->createMock( ASN::class );
		$asn->method( '__get' )
			->willReturnMap( [
				[ 'autonomousSystemNumber', 123 ],
				[ 'autonomousSystemOrganization', 'foobar' ]
			] );
		$reader = $this->createMock( Reader::class );
		$reader->method( 'asn' )
			->with( $ip )
			->willReturn( $asn );
		$reader->method( 'city' )
			->with( $ip )
			->willReturn( $city );

		$this->readerFactory->method( 'get' )
			->willReturn( $reader );

		$info = $this->getInfoRetriever()->retrieveFor( $user );

		$this->assertEquals( new Coordinates( 1.0, 2.0 ), $info->getCoordinates() );
		$this->assertSame( 123, $info->getAsn() );
		$this->assertEquals( 'foobar', $info->getOrganization() );
		$this->assertEquals( [ 'en' => 'bar' ], $info->getCountryNames() );
		$this->assertEquals( [ new Location( 1, 'bar' ) ], $info->getLocation() );
		$this->assertNull( $info->getIsp() );
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

		$this->tempUserIPLookup->method( 'getMostRecentAddress' )
			->with( $user )
			->willReturn( null );

		$this->readerFactory->expects( $this->never() )
			->method( 'get' );

		$info = $this->getInfoRetriever()->retrieveFor( $user );

		$this->assertEmptyInfo( $info );
	}

}
