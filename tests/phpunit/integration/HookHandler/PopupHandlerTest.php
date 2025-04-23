<?php
namespace MediaWiki\IPInfo\Test\Integration\HookHandler;

use MediaWiki\IPInfo\HookHandler\PopupHandler;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\IPInfo\HookHandler\PopupHandler
 */
class PopupHandlerTest extends MediaWikiIntegrationTestCase {
	public function testFactory(): void {
		$handler = PopupHandler::factory();

		$this->assertInstanceOf( PopupHandler::class, $handler );
	}
}
