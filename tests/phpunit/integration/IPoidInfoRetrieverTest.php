<?php

namespace MediaWiki\IPInfo\Test\Integration;

use LoggedServiceOptions;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\IPInfo\InfoRetriever\IPoidInfoRetriever;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;
use Psr\Log\LoggerInterface;
use TestAllServiceOptionsUsed;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\IPoidInfoRetriever
 */
class IPoidInfoRetrieverTest extends MediaWikiIntegrationTestCase {
	use TestAllServiceOptionsUsed;
	use MockHttpTrait;

	public function testRetrievefromIpNoIpoidUrl() {
		$this->overrideConfigValue( 'IPInfoIpoidUrl', false );
		$httpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$httpRequestFactory->expects( $this->never() )
			->method( 'create' );
		$infoRetriever = new IPoidInfoRetriever(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				IPoidInfoRetriever::CONSTRUCTOR_OPTIONS,
				MediaWikiServices::getInstance()->getMainConfig()
			),
			$httpRequestFactory,
			$this->createmock( LoggerInterface::class )
		);
		$info = $infoRetriever->retrieveFromIP( '127.0.0.1' );
	}

	public function testRetrievefromIpBadRequest() {
		$this->overrideConfigValue( 'IPInfoIpoidUrl', 'test' );
		$infoRetriever = new IPoidInfoRetriever(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				IPoidInfoRetriever::CONSTRUCTOR_OPTIONS,
				MediaWikiServices::getInstance()->getMainConfig()
			),
			$this->makeMockHttpRequestFactory(
				$this->makeFakeHttpRequest(
					$body = '',
					$responseStatus = 500
				)
			),
			$this->createmock( LoggerInterface::class )
		);
		$info = $infoRetriever->retrieveFromIP( '127.0.0.1' );
		$this->assertNull( $info->getBehaviors() );
		$this->assertNull( $info->getRisks() );
		$this->assertNull( $info->getConnectionTypes() );
		$this->assertNull( $info->getTunnelOperators() );
		$this->assertNull( $info->getProxies() );
		$this->assertNull( $info->getNumUsersOnThisIP() );
	}

	public function testRetrievefromIp() {
		$this->overrideConfigValue( 'IPInfoIpoidUrl', 'test' );
		$infoRetriever = new IPoidInfoRetriever(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				IPoidInfoRetriever::CONSTRUCTOR_OPTIONS,
				MediaWikiServices::getInstance()->getMainConfig()
			),
			$this->makeMockHttpRequestFactory(
				$this->makeFakeHttpRequest(
					$body = "{\"127.0.0.1\":{\"ip\":\"127.0.0.1\",\"org\":\"Organization 1\"," .
						"\"client_count\":10,\"types\":[\"UNKNOWN\"],\"conc_city\":\"\"," .
						"\"conc_state\":\"\",\"conc_country\":\"\",\"countries\":0," .
						"\"location_country\":\"VN\",\"risks\":[]," .
						"\"last_updated\":1704295688,\"proxies\":[\"3_PROXY\",\"1_PROXY\"]," .
						"\"behaviors\":[],\"tunnels\":[]}}",
					$responseStatus = 200
				)
			),
			$this->createmock( LoggerInterface::class )
		);
		$info = $infoRetriever->retrieveFromIP( '127.0.0.1' );
		$this->assertArrayEquals( [], $info->getBehaviors() );
		$this->assertArrayEquals( [], $info->getRisks() );
		$this->assertSame( [ "UNKNOWN" ], $info->getConnectionTypes() );
		$this->assertArrayEquals( [], $info->getTunnelOperators() );
		$this->assertArrayEquals( [ "3_PROXY", "1_PROXY" ], $info->getProxies() );
		$this->assertSame( 10, $info->getNumUsersOnThisIP() );
	}
}
