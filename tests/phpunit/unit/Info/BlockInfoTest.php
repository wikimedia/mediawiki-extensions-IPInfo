<?php

namespace MediaWiki\IPInfo\Test\Unit\Info;

use MediaWiki\IPInfo\Info\BlockInfo;
use MediaWiki\Json\FormatJson;
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

	public function testJsonSerialize() {
		$this->assertJsonStringEqualsJsonString(
			'{"numActiveBlocks":0}',
			FormatJson::encode( new BlockInfo() )
		);
	}
}
