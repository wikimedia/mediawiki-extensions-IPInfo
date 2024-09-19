<?php

namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use LoggedServiceOptions;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\IPInfo\Info\IPoidInfo;
use MediaWiki\IPInfo\InfoRetriever\IPoidInfoRetriever;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use MockHttpTrait;
use Psr\Log\NullLogger;
use TestAllServiceOptionsUsed;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\IPoidInfoRetriever
 */
class IPoidInfoRetrieverTest extends MediaWikiUnitTestCase {
	use TestAllServiceOptionsUsed;
	use MockHttpTrait;

	private function createIPoidInfoRetriever(
		HttpRequestFactory $httpRequestFactory,
		array $configOverrides = []
	): IPoidInfoRetriever {
		return new IPoidInfoRetriever(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				IPoidInfoRetriever::CONSTRUCTOR_OPTIONS,
				$configOverrides + [ 'IPInfoIpoidUrl' => 'test' ]
			),
			$httpRequestFactory,
			new NullLogger()
		);
	}

	public function testRetrieveForNoIpoidUrl() {
		$httpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$httpRequestFactory->expects( $this->never() )
			->method( 'create' );
		$infoRetriever = $this->createIPoidInfoRetriever(
			$httpRequestFactory,
			[ 'IPInfoIpoidUrl' => false ]
		);
		$infoRetriever->retrieveFor( new UserIdentityValue( 0, '127.0.0.1' ), '127.0.0.1' );
	}

	public function testRetrieveForBadRequest() {
		$infoRetriever = $this->createIPoidInfoRetriever(
			$this->makeMockHttpRequestFactory(
				$this->makeFakeHttpRequest(
					'',
					500
				)
			)
		);
		$info = $infoRetriever->retrieveFor( new UserIdentityValue( 0, '127.0.0.1' ), '127.0.0.1' );
		$this->assertNull( $info->getBehaviors() );
		$this->assertNull( $info->getRisks() );
		$this->assertNull( $info->getConnectionTypes() );
		$this->assertNull( $info->getTunnelOperators() );
		$this->assertNull( $info->getProxies() );
		$this->assertNull( $info->getNumUsersOnThisIP() );
	}

	public function testRetrieveForMissingTemporaryUserIPData() {
		$httpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$httpRequestFactory->expects( $this->never() )
			->method( $this->anything() );

		$infoRetriever = $this->createIPoidInfoRetriever( $httpRequestFactory );
		$user = new UserIdentityValue( 4, '~2024-8' );

		$info = $infoRetriever->retrieveFor( $user, null );

		$this->assertNull( $info->getBehaviors() );
		$this->assertNull( $info->getRisks() );
		$this->assertNull( $info->getConnectionTypes() );
		$this->assertNull( $info->getTunnelOperators() );
		$this->assertNull( $info->getProxies() );
		$this->assertNull( $info->getNumUsersOnThisIP() );
	}

	/**
	 * @dataProvider provideUsers
	 */
	public function testRetrieveFor( UserIdentity $user, string $ip ) {
		$infoRetriever = $this->createIPoidInfoRetriever(
			$this->makeMockHttpRequestFactory(
				$this->makeFakeHttpRequest(
					"{\"2001:db8::8a2e:370:7334\":{\"ip\":\"2001:db8::8a2e:370:7334\"," .
						"\"org\":\"Organization 1\"," .
						"\"client_count\":10,\"types\":[\"UNKNOWN\"],\"conc_city\":\"\"," .
						"\"conc_state\":\"\",\"conc_country\":\"\",\"countries\":0," .
						"\"location_country\":\"VN\",\"risks\":[]," .
						"\"last_updated\":1704295688,\"proxies\":[\"3_PROXY\",\"1_PROXY\"]," .
						"\"behaviors\":[],\"tunnels\":[]}}",
					200
				)
			)
		);

		$info = $infoRetriever->retrieveFor( $user, $ip );
		$this->assertArrayEquals( [], $info->getBehaviors() );
		$this->assertArrayEquals( [], $info->getRisks() );
		$this->assertSame( [ "UNKNOWN" ], $info->getConnectionTypes() );
		$this->assertArrayEquals( [], $info->getTunnelOperators() );
		$this->assertArrayEquals( [ "3_PROXY", "1_PROXY" ], $info->getProxies() );
		$this->assertSame( 10, $info->getNumUsersOnThisIP() );
	}

	public static function provideUsers(): iterable {
		yield 'anonymous user' => [
			new UserIdentityValue( 0, '2001:0db8:0000:0000:0000:8a2e:0370:7334' ),
			'2001:0db8:0000:0000:0000:8a2e:0370:7334'
		];

		yield 'temporary user' => [
			new UserIdentityValue( 4, '~2024-8' ),
			'2001:0db8:0000:0000:0000:8a2e:0370:7334'
		];
	}

	public function testRetrieveBatch(): void {
		$user = new UserIdentityValue( 4, '~2024-8' );
		$ips = [ '1.1.1.1', '2.2.2.2', '3.3.3.3', '4.4.4.4' ];

		$infoRetriever = $this->createIPoidInfoRetriever(
			$this->makeMockHttpRequestFactory(
				$this->makeFakeHttpMultiClient( [
					"{\"1.1.1.1\":{\"ip\":\"1.1.1.1\"," .
					"\"org\":\"Organization 1\"," .
					"\"client_count\":10,\"types\":[\"UNKNOWN\"],\"conc_city\":\"\"," .
					"\"conc_state\":\"\",\"conc_country\":\"\",\"countries\":0," .
					"\"location_country\":\"VN\",\"risks\":[]," .
					"\"last_updated\":1704295688,\"proxies\":[\"3_PROXY\",\"1_PROXY\"]," .
					"\"behaviors\":[],\"tunnels\":[]}}",
					// no data
					[ 'code' => 404 ],
					// mismatched data
					"{\"5.5.5.5\":{\"ip\":\"5.5.5.5\"," .
					"\"org\":\"Organization 1\"," .
					"\"client_count\":10,\"types\":[\"UNKNOWN\"],\"conc_city\":\"\"," .
					"\"conc_state\":\"\",\"conc_country\":\"\",\"countries\":0," .
					"\"location_country\":\"VN\",\"risks\":[]," .
					"\"last_updated\":1704295688,\"proxies\":[\"2_PROXY\",\"5_PROXY\"]," .
					"\"behaviors\":[],\"tunnels\":[]}}",
					"{\"4.4.4.4\":{\"ip\":\"4.4.4.4\"," .
					"\"org\":\"Organization 2\"," .
					"\"client_count\":10,\"types\":[\"UNKNOWN\"],\"conc_city\":\"\"," .
					"\"conc_state\":\"\",\"conc_country\":\"\",\"countries\":0," .
					"\"location_country\":\"US\",\"risks\":[]," .
					"\"last_updated\":1704295688,\"proxies\":[\"4_PROXY\",\"3_PROXY\"]," .
					"\"behaviors\":[],\"tunnels\":[]}}",
				] )
			)
		);

		$infosByIp = $infoRetriever->retrieveBatch( $user, $ips );

		$this->assertSame( $ips, array_keys( $infosByIp ) );
		$this->assertContainsOnlyInstancesOf( IPoidInfo::class, $infosByIp );

		$this->assertSame( [ '3_PROXY', '1_PROXY' ], $infosByIp['1.1.1.1']->getProxies() );
		$this->assertNull( $infosByIp['2.2.2.2']->getProxies() );
		$this->assertNull( $infosByIp['3.3.3.3']->getProxies() );
		$this->assertSame( [ '4_PROXY', '3_PROXY' ], $infosByIp['4.4.4.4']->getProxies() );
	}

	public function testRetrieveBatchWhenServiceDisabled(): void {
		$user = new UserIdentityValue( 4, '~2024-8' );
		$ips = [ '1.1.1.1', '2.2.2.2', '3.3.3.3', '4.4.4.4' ];

		$infoRetriever = $this->createIPoidInfoRetriever(
			$this->makeMockHttpRequestFactory(),
			[ 'IPInfoIpoidUrl' => false ]
		);

		$infosByIp = $infoRetriever->retrieveBatch( $user, $ips );

		$this->assertSame( $ips, array_keys( $infosByIp ) );
		$this->assertContainsOnlyInstancesOf( IPoidInfo::class, $infosByIp );
	}
}
