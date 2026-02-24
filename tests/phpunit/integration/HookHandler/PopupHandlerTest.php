<?php
namespace MediaWiki\IPInfo\Test\Integration\HookHandler;

use MediaWiki\IPInfo\HookHandler\PopupHandler;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\IPInfo\HookHandler\PopupHandler
 */
class PopupHandlerTest extends MediaWikiIntegrationTestCase {
	public function testSuccessfullyConstructs(): void {
		$handler = new PopupHandler(
			$this->getServiceContainer()->getService( 'IPInfoPermissionManager' ),
		);

		$this->assertInstanceOf( PopupHandler::class, $handler );
	}
}
