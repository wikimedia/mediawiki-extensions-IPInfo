<?php

namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use Generator;
use MediaWiki\Block\AbstractBlock;
use MediaWiki\Block\BlockManager;
use MediaWiki\Block\CompositeBlock;
use MediaWiki\IPInfo\Info\BlockInfo;
use MediaWiki\IPInfo\InfoRetriever\BlockInfoRetriever;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IDatabase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoManager
 */
class BlockInfoRetrieverTest extends MediaWikiUnitTestCase {
	public function provideRetrieveFromIP(): Generator {
		yield [ null, 0, 0, 0, 0 ];
		yield [ null, 1, 0, 0, 1 ];

		$block = $this->createMock( AbstractBlock::class );

		yield [ $block, 1, 0, 1, 0 ];
		yield [ $block, 2, 0, 1, 1 ];

		$block2 = $this->createMock( AbstractBlock::class );
		$block3 = new CompositeBlock( [
			'originalBlocks' => [ $block, $block2 ]
		] );

		yield [ $block3, 3, 0, 2, 1 ];

		// ---
		//
		// Handling of unblock rows in the logging table.

		yield [ null, 2, 2, 0, 0 ];
		yield [ null, 2, 1, 0, 1 ];
		yield [ $block, 3, 1, 1, 1 ];
	}

	/**
	 * @dataProvider provideRetrieveFromIP
	 */
	public function testRetrieveFromIP(
		$userBlock,
		$numLoggingBlockRows,
		$numLoggingUnblockRows,
		$expectedNumActiveBlocks,
		$expectedNumPastBlocks
	) {
		$ip = '127.0.0.1';

		$blockManager = $this->createMock( BlockManager::class );
		$database = $this->createMock( IDatabase::class );

		$blockManager->expects( $this->once() )
			->method( 'getUserBlock' )
			->willReturn( $userBlock );

		$database->expects( $this->at( 0 ) )
			->method( 'selectRowCount' )
			->with(
				'logging',
				'*',
				[
					'log_type' => 'block',
					'log_action' => 'block',
					'log_namespace' => NS_USER,
					'log_title' => $ip,
				]
			)
			->willReturn( $numLoggingBlockRows );

		$database->expects( $this->at( 1 ) )
			->method( 'selectRowCount' )
			->with(
				'logging',
				'*',
				[
					'log_type' => 'block',
					'log_action' => 'unblock',
					'log_namespace' => NS_USER,
					'log_title' => $ip,
				]
			)
			->willReturn( $numLoggingUnblockRows );

		$retriever = new BlockInfoRetriever( $blockManager, $database );
		$info = $retriever->retrieveFromIP( $ip );

		$this->assertInstanceOf( BlockInfo::class, $info );
		$this->assertEquals( $expectedNumActiveBlocks, $info->getNumActiveBlocks() );
		$this->assertEquals( $expectedNumPastBlocks, $info->getNumPastBlocks() );
	}

}
