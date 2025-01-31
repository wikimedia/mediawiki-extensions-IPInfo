<?php

namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use MediaWiki\Block\AbstractBlock;
use MediaWiki\Block\BlockManager;
use MediaWiki\Block\CompositeBlock;
use MediaWiki\Block\SystemBlock;
use MediaWiki\IPInfo\Info\BlockInfo;
use MediaWiki\IPInfo\InfoRetriever\BlockInfoRetriever;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\BlockInfoRetriever
 */
class BlockInfoRetrieverTest extends MediaWikiUnitTestCase {
	private BlockManager $blockManager;

	private BlockInfoRetriever $retriever;

	protected function setUp(): void {
		parent::setUp();

		$this->blockManager = $this->createMock( BlockManager::class );
		$userIdentityUtils = $this->createMock( UserIdentityUtils::class );
		$userIdentityUtils->method( 'isTemp' )
			->willReturnCallback( static fn ( UserIdentity $user ) => $user->isRegistered() );

		$this->retriever = new BlockInfoRetriever( $this->blockManager, $userIdentityUtils );
	}

	public function testShouldHaveProperName(): void {
		$this->assertSame( 'ipinfo-source-block', $this->retriever->getName() );
	}

	/**
	 * @dataProvider provideUsers
	 */
	public function testRetrieveSingleBlock( UserIdentity $user ) {
		if ( $user->isRegistered() ) {
			$this->blockManager->method( 'getBlock' )
				->with( $user, null )
				->willReturn( new SystemBlock() );

		} else {
			$this->blockManager->method( 'getIpBlock' )
				->with( $user, true )
				->willReturn( new SystemBlock() );
		}

		$info = $this->retriever->retrieveFor( $user, '127.0.0.1' );

		$this->assertInstanceOf( BlockInfo::class, $info );
		$this->assertSame( 1, $info->getNumActiveBlocks() );
	}

	/**
	 * @dataProvider provideUsers
	 */
	public function testRetrieveCompositeBlock( UserIdentity $user ) {
		$block = new CompositeBlock( [
			'originalBlocks' => [
				$this->createMock( AbstractBlock::class ),
				$this->createMock( AbstractBlock::class )
			]
		] );

		if ( $user->isRegistered() ) {
			$this->blockManager->method( 'getBlock' )
				->with( $user, null )
				->willReturn( $block );

		} else {
			$this->blockManager->method( 'getIpBlock' )
				->with( $user, true )
				->willReturn( $block );
		}

		$info = $this->retriever->retrieveFor( $user, '127.0.0.1' );

		$this->assertInstanceOf( BlockInfo::class, $info );
		$this->assertSame( 2, $info->getNumActiveBlocks() );
	}

	public static function provideUsers(): iterable {
		yield 'anonymous user' => [ new UserIdentityValue( 0, '127.0.0.1' ) ];
		yield 'temporary user' => [ new UserIdentityValue( 6, '~2024-8' ) ];
	}

	public function testRetrieveIgnoringAutoblock() {
		$user = new UserIdentityValue( 0, '127.0.0.1' );
		$autoBlock = $this->createMock( AbstractBlock::class );
		$autoBlock->method( 'getType' )
			->willReturn( AbstractBlock::TYPE_AUTO );
		$autoBlock->method( 'toArray' )
			->willReturn( [ $autoBlock ] );

		$this->blockManager->method( 'getIpBlock' )
			->with( $user, true )
			->willReturn( $autoBlock );

		$info = $this->retriever->retrieveFor( $user, '127.0.0.1' );

		$this->assertInstanceOf( BlockInfo::class, $info );
		$this->assertSame( 0, $info->getNumActiveBlocks() );
	}

	public function testRetrieveCompositeBlockIgnoringAutoblock() {
		$user = new UserIdentityValue( 0, '127.0.0.1' );
		$autoBlock = $this->createMock( AbstractBlock::class );
		$autoBlock->method( 'getType' )
			->willReturn( AbstractBlock::TYPE_AUTO );

		$block = new CompositeBlock( [
			'originalBlocks' => [
				$autoBlock,
				$this->createMock( AbstractBlock::class )
			]
		] );

		$this->blockManager->method( 'getIpBlock' )
			->with( $user, true )
			->willReturn( $block );

		$info = $this->retriever->retrieveFor( $user, '127.0.0.1' );

		$this->assertInstanceOf( BlockInfo::class, $info );
		$this->assertSame( 1, $info->getNumActiveBlocks() );
	}

}
