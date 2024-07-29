<?php

namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use Generator;
use MediaWiki\Block\AbstractBlock;
use MediaWiki\Block\BlockManager;
use MediaWiki\Block\CompositeBlock;
use MediaWiki\Block\SystemBlock;
use MediaWiki\IPInfo\Info\BlockInfo;
use MediaWiki\IPInfo\InfoRetriever\BlockInfoRetriever;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IDatabase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\BlockInfoRetriever
 */
class BlockInfoRetrieverTest extends MediaWikiUnitTestCase {
	public function provideRetrieveFromIP(): Generator {
		yield [ null, 0 ];

		$block = new SystemBlock();

		yield [ $block, 1 ];

		$block2 = $this->createMock( AbstractBlock::class );
		$block3 = new CompositeBlock( [
			'originalBlocks' => [ $block, $block2 ]
		] );

		yield [ $block3, 2 ];
	}

	/**
	 * @dataProvider provideRetrieveFromIP
	 */
	public function testRetrieveFromIP(
		$block,
		$expectedNumActiveBlocks
	) {
		$user = new UserIdentityValue( 0, '127.0.0.1' );

		$blockManager = $this->createMock( BlockManager::class );
		$database = $this->createMock( IDatabase::class );

		$blockManager->expects( $this->once() )
			->method( 'getIpBlock' )
			->willReturn( $block );

		$retriever = new BlockInfoRetriever( $blockManager, $database );
		$this->assertSame( 'ipinfo-source-block', $retriever->getName() );
		$info = $retriever->retrieveFor( $user );

		$this->assertInstanceOf( BlockInfo::class, $info );
		$this->assertEquals( $expectedNumActiveBlocks, $info->getNumActiveBlocks() );
	}

}
