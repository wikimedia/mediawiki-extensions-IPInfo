<?php

namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use MediaWiki\Extension\IPReputation\IPoid\IPoidResponse;
use MediaWiki\Extension\IPReputation\Services\IPReputationIPoidDataLookup;
use MediaWiki\IPInfo\InfoRetriever\IPoidInfoRetriever;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\IPoidInfoRetriever
 */
class IPoidInfoRetrieverTest extends MediaWikiUnitTestCase {

	public function setUp(): void {
		if ( !class_exists( IPoidResponse::class ) ) {
			$this->markTestSkipped( "Extension 'IPReputation' is not installed" );
		}
	}

	private function createIPoidInfoRetriever(
		IPReputationIPoidDataLookup $lookup
	): IPoidInfoRetriever {
		return new IPoidInfoRetriever( $lookup );
	}

	public function testGetName(): void {
		$lookup = $this->createMock( IPReputationIPoidDataLookup::class );
		$retriever = $this->createIPoidInfoRetriever( $lookup );
		$this->assertSame( IPoidInfoRetriever::NAME, $retriever->getName() );
	}

	public function testRetrieveForReturnsEmptyResponseWhenIpNull(): void {
		$lookup = $this->createMock( IPReputationIPoidDataLookup::class );
		$retriever = $this->createIPoidInfoRetriever( $lookup );
		$user = new UserIdentityValue( 1, 'TestUser' );
		$result = $retriever->retrieveFor( $user, null );
		$this->assertEquals( IPoidResponse::newFromArray( [] ), $result );
	}

	public function testRetrieveForDelegatesToLookup(): void {
		$ip = '1.2.3.4';
		$user = new UserIdentityValue( 1, 'TestUser' );
		$mockResponse = $this->createMock( IPoidResponse::class );

		$lookup = $this->createMock( IPReputationIPoidDataLookup::class );
		$lookup->expects( $this->once() )
			->method( 'getIPoidDataForIp' )
			->with(
				$ip,
				IPoidInfoRetriever::class . '::retrieveFor'
			)
			->willReturn( $mockResponse );

		$retriever = $this->createIPoidInfoRetriever( $lookup );
		$result = $retriever->retrieveFor( $user, $ip );

		$this->assertSame( $mockResponse, $result );
	}

	public function testRetrieveBatch(): void {
		$ips = [ '1.1.1.1', '2.2.2.2' ];
		$user = new UserIdentityValue( 1, 'TestUser' );

		$response1 = $this->createMock( IPoidResponse::class );
		$response2 = $this->createMock( IPoidResponse::class );

		$lookup = $this->createMock( IPReputationIPoidDataLookup::class );

		$lookup->expects( $this->exactly( 2 ) )
			->method( 'getIPoidDataForIp' )
			->willReturnCallback( static function ( $ip, $method ) use ( $response1, $response2 ) {
				if ( $method !== IPoidInfoRetriever::class . '::retrieveBatch' ) {
					return null;
				}
				if ( $ip === '1.1.1.1' ) {
					return $response1;
				}
				if ( $ip === '2.2.2.2' ) {
					return $response2;
				}
				return null;
			} );

		$retriever = $this->createIPoidInfoRetriever( $lookup );
		$results = $retriever->retrieveBatch( $user, $ips );

		$this->assertCount( 2, $results );
		$this->assertSame( $response1, $results['1.1.1.1'] );
		$this->assertSame( $response2, $results['2.2.2.2'] );
	}
}
