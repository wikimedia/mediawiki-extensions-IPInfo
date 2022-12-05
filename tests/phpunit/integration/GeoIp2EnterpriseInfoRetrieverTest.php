<?php

namespace MediaWiki\IPInfo\Test\Integration;

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
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use TestAllServiceOptionsUsed;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\GeoIp2EnterpriseInfoRetriever
 */
class GeoIp2EnterpriseInfoRetrieverTest extends MediaWikiIntegrationTestCase {
	use TestAllServiceOptionsUsed;

	public function testNoGeoIP2EnterprisePath() {
		$reader = $this->createMock( Reader::class );
		$readerFactory = $this->createMock( ReaderFactory::class );
		$readerFactory->method( 'get' )
			->willReturn( $reader );
		$readerFactory->expects( $this->never() )
			->method( 'get' );

		$infoRetriever = new GeoIp2EnterpriseInfoRetriever(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				GeoIp2EnterpriseInfoRetriever::CONSTRUCTOR_OPTIONS,
				MediaWikiServices::getInstance()->getMainConfig()
			),
			$readerFactory
		);
		$info = $infoRetriever->retrieveFromIP( '127.0.0.1' );
	}

	public function testNullRetrieveFromIP() {
		$this->setMwGlobals( [
			'wgIPInfoGeoIP2EnterprisePath' => 'test',
		] );
		$ip = '127.0.0.1';

		$reader = $this->createMock( Reader::class );
		$reader->method( 'enterprise' )
			->willThrowException(
				new AddressNotFoundException()
			);
		$reader->method( 'anonymousIp' )
			->willThrowException(
				new AddressNotFoundException()
			);

		$readerFactory = $this->createMock( ReaderFactory::class );
		$readerFactory->method( 'get' )
			->willReturn( $reader );
		$readerFactory->expects( $this->atLeastOnce() )
			->method( 'get' );

		$infoRetriever = new GeoIp2EnterpriseInfoRetriever(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				GeoIp2EnterpriseInfoRetriever::CONSTRUCTOR_OPTIONS,
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
			'wgIPInfoGeoIP2EnterprisePath' => 'test',
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
		$readerFactory = $this->createMock( ReaderFactory::class );
		$readerFactory->method( 'get' )
			->willReturn( $reader );

		$infoRetriever = new GeoIp2EnterpriseInfoRetriever(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				GeoIp2EnterpriseInfoRetriever::CONSTRUCTOR_OPTIONS,
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
		$this->assertEquals( new ProxyType( true, true, true, true, true, true ), $info->getProxyType() );
	}

}
