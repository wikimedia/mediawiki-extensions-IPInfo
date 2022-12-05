<?php

namespace MediaWiki\IPInfo\Test\Integration;

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
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use TestAllServiceOptionsUsed;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\GeoLite2InfoRetriever
 */
class GeoLite2InfoRetrieverTest extends MediaWikiIntegrationTestCase {
	use TestAllServiceOptionsUsed;

	public function testNoGeoLite2Prefix() {
		$reader = $this->createMock( Reader::class );
		$readerFactory = $this->createMock( ReaderFactory::class );
		$readerFactory->method( 'get' )
			->willReturn( $reader );
		$readerFactory->expects( $this->never() )
			->method( 'get' );

		$infoRetriever = new GeoLite2InfoRetriever(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				GeoLite2InfoRetriever::CONSTRUCTOR_OPTIONS,
				MediaWikiServices::getInstance()->getMainConfig()
			),
			$readerFactory
		);
		$infoRetriever->retrieveFromIP( '127.0.0.1' );
	}

	public function testNullRetrieveFromIP() {
		$this->setMwGlobals( [
			'wgIPInfoGeoLite2Prefix' => 'test',
		] );

		$reader = $this->createMock( Reader::class );
		$reader->method( 'asn' )
			->willThrowException(
				new AddressNotFoundException()
			);
		$reader->method( 'city' )
			->willThrowException(
				new AddressNotFoundException()
			);

		$readerFactory = $this->createMock( ReaderFactory::class );
		$readerFactory->method( 'get' )
			->willReturn( $reader );
		$readerFactory->expects( $this->atLeastOnce() )
			->method( 'get' );

		$infoRetriever = new GeoLite2InfoRetriever(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				GeoLite2InfoRetriever::CONSTRUCTOR_OPTIONS,
				MediaWikiServices::getInstance()->getMainConfig()
			),
			$readerFactory
		);
		$info = $infoRetriever->retrieveFromIP( '127.0.0.1' );

		$this->assertInstanceOf( Info::class, $info );
		$this->assertSame( 'ipinfo-source-geoip2', $infoRetriever->getName() );
		$this->assertNull( $info->getCoordinates() );
		$this->assertNull( $info->getAsn() );
		$this->assertNull( $info->getOrganization() );
		$this->assertNull( $info->getCountry() );
		$this->assertNull( $info->getLocation() );
		$this->assertNull( $info->getIsp() );
		$this->assertNull( $info->getConnectionType() );
		$this->assertNull( $info->getUserType() );
		$this->assertNull( $info->getProxyType() );
	}

	public function testRetrieveFromIP() {
		$this->setMwGlobals( [
			'wgIPInfoGeoLite2Prefix' => 'test',
		] );
		$ip = '127.0.0.1';

		$location = $this->createMock( LocationRecord::class );
		$country = $this->createMock( Country::class );
		$country->method( '__get' )
			->willReturnMap( [
				[ 'geonameId', 1 ],
				[ 'name', 'bar' ]
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

		$readerFactory = $this->createMock( ReaderFactory::class );
		$readerFactory->method( 'get' )
			->willReturn( $reader );

		$infoRetriever = new GeoLite2InfoRetriever(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				GeoLite2InfoRetriever::CONSTRUCTOR_OPTIONS,
				MediaWikiServices::getInstance()->getMainConfig()
			),
			$readerFactory
		);
		$info = $infoRetriever->retrieveFromIP( '127.0.0.1' );

		$this->assertEquals( new Coordinates( 1.0, 2.0 ), $info->getCoordinates() );
		$this->assertSame( 123, $info->getAsn() );
		$this->assertEquals( 'foobar', $info->getOrganization() );
		$this->assertEquals( [ new Location( 1, 'bar' ) ], $info->getCountry() );
		$this->assertEquals( [ new Location( 1, 'bar' ) ], $info->getLocation() );
		$this->assertNull( $info->getIsp() );
		$this->assertNull( $info->getConnectionType() );
		$this->assertNull( $info->getUserType() );
		$this->assertNull( $info->getProxyType() );
	}

}
