<?php
namespace MediaWiki\IPInfo\Test\Unit\HookHandler;

use MediaWiki\IPInfo\HookHandler\SpecialPageInitListHandler;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\IPInfo\HookHandler\SpecialPageInitListHandler
 */
class SpecialPageInitListHandlerTest extends MediaWikiUnitTestCase {
	private TempUserConfig $tempUserConfig;

	private SpecialPageInitListHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		$this->tempUserConfig = $this->createMock( TempUserConfig::class );
		$this->handler = new SpecialPageInitListHandler( $this->tempUserConfig );
	}

	public function testShouldDoNothingWhenTempUsersAreNotKnown(): void {
		$this->tempUserConfig->method( 'isKnown' )
			->willReturn( false );

		$list = [];

		$this->handler->onSpecialPage_initList( $list );

		$this->assertSame( [], $list );
	}

	public function testShouldRegisterSpecialPageIfTempUsersAreKnown(): void {
		$this->tempUserConfig->method( 'isKnown' )
			->willReturn( true );

		$list = [];

		$this->handler->onSpecialPage_initList( $list );

		$this->assertArrayHasKey( 'IPInfo', $list );
	}
}
