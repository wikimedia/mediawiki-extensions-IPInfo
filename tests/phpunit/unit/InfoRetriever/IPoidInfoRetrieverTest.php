<?php

namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use LoggedServiceOptions;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\IPInfo\InfoRetriever\IPoidInfoRetriever;
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
		$infoRetriever->retrieveFor( new UserIdentityValue( 0, '127.0.0.1' ) );
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
		$info = $infoRetriever->retrieveFor( new UserIdentityValue( 0, '127.0.0.1' ) );
		$this->assertNull( $info->getBehaviors() );
		$this->assertNull( $info->getRisks() );
		$this->assertNull( $info->getConnectionTypes() );
		$this->assertNull( $info->getTunnelOperators() );
		$this->assertNull( $info->getProxies() );
		$this->assertNull( $info->getNumUsersOnThisIP() );
	}

	public function testRetrieveFor() {
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
		$user = new UserIdentityValue( 0, '2001:0db8:0000:0000:0000:8a2e:0370:7334' );
		$info = $infoRetriever->retrieveFor( $user );
		$this->assertArrayEquals( [], $info->getBehaviors() );
		$this->assertArrayEquals( [], $info->getRisks() );
		$this->assertSame( [ "UNKNOWN" ], $info->getConnectionTypes() );
		$this->assertArrayEquals( [], $info->getTunnelOperators() );
		$this->assertArrayEquals( [ "3_PROXY", "1_PROXY" ], $info->getProxies() );
		$this->assertSame( 10, $info->getNumUsersOnThisIP() );
	}
}
