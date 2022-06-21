<?php

namespace MediaWiki\IPInfo\Test\Unit\Info;

use MediaWiki\IPInfo\Info\BlockInfo;
use MediaWikiUnitTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\Info\BlockInfo
 */
class BlockInfoTest extends MediaWikiUnitTestCase {
	public function testDefaultValues() {
		$info = new BlockInfo();

		$this->assertSame( 0, $info->getNumActiveBlocks() );
	}
}
