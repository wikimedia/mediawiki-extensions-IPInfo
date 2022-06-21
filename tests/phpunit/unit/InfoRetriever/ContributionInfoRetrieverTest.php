<?php

namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use MediaWiki\IPInfo\Info\ContributionInfo;
use MediaWiki\IPInfo\InfoRetriever\ContributionInfoRetriever;
use MediaWikiUnitTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IDatabase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\ContributionInfoRetriever
 */
class ContributionInfoRetrieverTest extends MediaWikiUnitTestCase {
	public function testRetrieveFromIP() {
		$ip = '1.1.1.1';
		$database = $this->createMock( IDatabase::class );
		$expectedIP = IPUtils::toHex( $ip );
		$numLocalEdits = 42;
		$numRecentEdits = 24;

		$database->expects( $this->at( 0 ) )
			->method( 'selectRowCount' )
			->with(
			'ip_changes',
			'*',
			[
				'ipc_hex' => $expectedIP,
			]
		)
		->willReturn( $numLocalEdits );

		$database->expects( $this->once() )
		->method( 'addQuotes' )
		->with( $this->anything() )
		->willReturn( 30 );

		$database->expects( $this->at( 3 ) )
			->method( 'selectRowCount' )
			->with(
			'ip_changes',
			'*',
			[
				'ipc_hex' => $expectedIP,
				'ipc_rev_timestamp > 30',
			]
		)
		->willReturn( $numRecentEdits );

		$retriever = new ContributionInfoRetriever( $database );
		$this->assertSame( 'ipinfo-source-contributions', $retriever->getName() );
		$info = $retriever->retrieveFromIP( $ip );

		$this->assertInstanceOf( ContributionInfo::class, $info );
		$this->assertEquals( $numLocalEdits, $info->getNumLocalEdits() );
		$this->assertEquals( $numRecentEdits, $info->getNumRecentEdits() );
	}
}
