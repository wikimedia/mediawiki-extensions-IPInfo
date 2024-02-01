<?php

namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use MediaWiki\IPInfo\Info\ContributionInfo;
use MediaWiki\IPInfo\InfoRetriever\ContributionInfoRetriever;
use MediaWikiUnitTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
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
		$provider = $this->createMock( IConnectionProvider::class );
		$database = $this->createMock( IDatabase::class );
		$provider->method( 'getReplicaDatabase' )->willReturn( $database );
		$numLocalEdits = 42;
		$numRecentEdits = 24;
		$numDeletedEdits = 10;

		$database->method( 'addQuotes' )
			->willReturn( 30 );

		$map = [
			[
				[ 'ip_changes' ],
				'*',
				[
					'ipc_hex' => $expectedIP
				],
				ContributionInfoRetriever::class . '::retrieveFromIP',
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
				ContributionInfoRetriever::class . '::retrieveFromIP',
				[],
				[],
				$numRecentEdits,
			],
			[
				[ 0 => 'archive', 'actor' => 'actor' ],
				'*',
				[ 'actor_name' => $ip ],
				ContributionInfoRetriever::class . '::retrieveFromIP',
				[],
				[ 'actor' => [
					'JOIN',
					'actor_id=ar_actor'
				] ],
				$numDeletedEdits,
			],
		];

		$database->method( 'newSelectQueryBuilder' )
			->willReturnOnConsecutiveCalls(
				new SelectQueryBuilder( $database ),
				new SelectQueryBuilder( $database ),
				new SelectQueryBuilder( $database )
			);
		$database->method( 'selectRowCount' )
			->willReturnMap( $map );

		$retriever = new ContributionInfoRetriever( $provider );
		$this->assertSame( 'ipinfo-source-contributions', $retriever->getName() );
		$info = $retriever->retrieveFromIP( $ip );

		$this->assertInstanceOf( ContributionInfo::class, $info );
		$this->assertEquals( $numLocalEdits, $info->getNumLocalEdits() );
		$this->assertEquals( $numRecentEdits, $info->getNumRecentEdits() );
		$this->assertEquals( $numDeletedEdits, $info->getNumDeletedEdits() );
	}
}
