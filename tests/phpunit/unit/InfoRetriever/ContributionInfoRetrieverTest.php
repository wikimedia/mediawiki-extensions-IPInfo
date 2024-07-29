<?php

namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use MediaWiki\IPInfo\Info\ContributionInfo;
use MediaWiki\IPInfo\InfoRetriever\ContributionInfoRetriever;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\Expression;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\ContributionInfoRetriever
 */
class ContributionInfoRetrieverTest extends MediaWikiUnitTestCase {
	private IConnectionProvider $connectionProvider;

	private ContributionInfoRetriever $contributionInfoRetriever;

	protected function setUp(): void {
		parent::setUp();

		$this->connectionProvider = $this->createMock( IConnectionProvider::class );

		$this->contributionInfoRetriever = new ContributionInfoRetriever(
			$this->connectionProvider
		);
	}

	public function testRetrieve() {
		$user = new UserIdentityValue( 0, '127.0.0.1' );
		$ip = $user->getName();
		$expectedIP = IPUtils::toHex( $ip );

		$database = $this->createMock( IDatabase::class );
		$this->connectionProvider->method( 'getReplicaDatabase' )->willReturn( $database );

		$numLocalEdits = 42;
		$numRecentEdits = 24;
		$numDeletedEdits = 10;

		$expr = $this->createMock( Expression::class );
		$expr->method( 'toSql' )->willReturn( 'rev_timestamp > 30' );
		$database->method( 'expr' )
			->willReturn( $expr );

		$fname = ContributionInfoRetriever::class . '::retrieveFor';

		$map = [
			[
				[ 'ip_changes' ],
				'*',
				[
					'ipc_hex' => $expectedIP
				],
				$fname,
				[],
				[],
				$numLocalEdits,
			],
			[
				[ 'ip_changes' ],
				'*',
				[
					'ipc_hex' => $expectedIP,
					$expr,
				],
				$fname,
				[],
				[],
				$numRecentEdits,
			],
			[
				[ 0 => 'archive', 'actor' => 'actor' ],
				'*',
				[ 'actor_name' => $ip ],
				$fname,
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

		$this->assertSame( 'ipinfo-source-contributions', $this->contributionInfoRetriever->getName() );
		$info = $this->contributionInfoRetriever->retrieveFor( $user );

		$this->assertInstanceOf( ContributionInfo::class, $info );
		$this->assertEquals( $numLocalEdits, $info->getNumLocalEdits() );
		$this->assertEquals( $numRecentEdits, $info->getNumRecentEdits() );
		$this->assertEquals( $numDeletedEdits, $info->getNumDeletedEdits() );
	}
}
