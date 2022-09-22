<?php

namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use MediaWiki\IPInfo\Info\ContributionInfo;
use MediaWiki\IPInfo\InfoRetriever\ContributionInfoRetriever;
use MediaWikiUnitTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\ContributionInfoRetriever
 */
class ContributionInfoRetrieverTest extends MediaWikiUnitTestCase {
	public function testRetrieveFromIP() {
		$ip = '1.1.1.1';
		$expectedIP = IPUtils::toHex( $ip );
		$database = $this->createMock( IDatabase::class );
		$numLocalEdits = 42;
		$numRecentEdits = 24;

		$database->method( 'addQuotes' )
			->willReturn( 30 );

		$map = [
			[
				[ 'ip_changes' ],
				'*',
				[
					'ipc_hex' => $expectedIP
				],
				'MediaWiki\IPInfo\InfoRetriever\ContributionInfoRetriever::retrieveFromIP',
				[],
				[],
				$numLocalEdits,
			],
			[
				[ 'ip_changes' ],
				'*',
				[
					'ipc_hex' => $expectedIP,
					'ipc_rev_timestamp > 30',
				],
				'MediaWiki\IPInfo\InfoRetriever\ContributionInfoRetriever::retrieveFromIP',
				[],
				[],
				$numRecentEdits,
			],
		];

		$database->method( 'newSelectQueryBuilder' )
			->willReturnOnConsecutiveCalls(
				new SelectQueryBuilder( $database ),
				new SelectQueryBuilder( $database )
			);
		$database->method( 'selectRowCount' )
			->will( $this->returnValueMap( $map ) );

		$retriever = new ContributionInfoRetriever( $database );
		$this->assertSame( 'ipinfo-source-contributions', $retriever->getName() );
		$info = $retriever->retrieveFromIP( $ip );

		$this->assertInstanceOf( ContributionInfo::class, $info );
		$this->assertEquals( $numLocalEdits, $info->getNumLocalEdits() );
		$this->assertEquals( $numRecentEdits, $info->getNumRecentEdits() );
	}
}
