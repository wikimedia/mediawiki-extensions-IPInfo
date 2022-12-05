<?php

namespace MediaWiki\IPInfo\Test\Unit\HookHandler;

use FauxRequest;
use MediaWiki\IPInfo\HookHandler\PopupHandler;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiUnitTestCase;
use OutputPage;
use Skin;
use Title;
use User;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\HookHandler\PopupHandler
 */
class PopupHandlerTest extends MediaWikiUnitTestCase {

	private function getPermissionManager() {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );
		return $permissionManager;
	}

	private function getUserOptionsLookup() {
		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->willReturn( true );
		return $userOptionsLookup;
	}

	private function getPopupHandler( $overrides = null ) {
		return new PopupHandler(
			$overrides[ 'PermissionManager' ] ?? $this->getPermissionManager(),
			$overrides[ 'UserOptionsLookup' ] ?? $this->getUserOptionsLookup()
		);
	}

	private function getOutputPage( $overrides = null ) {
		$out = $this->getMockBuilder( OutputPage::class )
			->disableOriginalConstructor()
			->setMethodsExcept( [
				'addModules',
				'getModules',
			] )
			->getMock();
		$out->method( 'getUser' )
			->willReturn( $overrides['user'] ?? $this->createMock( User::class ) );
		$out->method( 'getRequest' )
			->willReturn( $overrides['request'] ?? $this->createMock( FauxRequest::class ) );
		$out->method( 'getTitle' )
			->willReturn( $overrides['title'] ?? $this->createMock( Title::class ) );
		return $out;
	}

	/**
	 * @dataProvider provideOnBeforePageDisplayTitles
	 */
	public function testOnBeforePageDisplayTitles( $special, $expected ) {
		$title = $this->createMock( Title::class );
		$title->method( 'isSpecial' )
			->willReturnMap( [
				[ $special, true ],
			] );

		$out = $this->getOutputPage( [
			'title' => $title,
		] );

		$handler = $this->getPopupHandler();
		$handler->onBeforePageDisplay( $out, $this->createMock( Skin::class ) );
		$this->assertSame( $expected, in_array( 'ext.ipInfo', $out->getModules() ) );
	}

	public function provideOnBeforePageDisplayTitles() {
		return [
			'displays on Special:Log' => [ 'Log', true ],
			'displays on Special:RecentChanges' => [ 'Recentchanges', true ],
			'displays on Special:Watchlist' => [ 'Watchlist', true ],
			'doesn\'t display on Special:AllPages' => [ 'AllPages', false ],
		];
	}

	/**
	 * @dataProvider provideOnBeforePageDisplayActions
	 */
	public function testOnBeforePageDisplayActions( $action, $expected ) {
		$request = new FauxRequest( [
			'action' => $action,
		] );

		$out = $this->getOutputPage( [
			'request' => $request,
		] );

		$handler = $this->getPopupHandler();
		$handler->onBeforePageDisplay( $out, $this->createMock( Skin::class ) );
		$this->assertSame( $expected, in_array( 'ext.ipInfo', $out->getModules() ) );
	}

	public function provideOnBeforePageDisplayActions() {
		return [
			'displays on history' => [ 'history', true ],
			'doesn\'t display on read' => [ 'read', false ],
		];
	}

	/**
	 * @dataProvider provideOnBeforePageDisplayPermissions
	 */
	public function testOnBeforePageDisplayPermissions( $permission, $expected ) {
		$user = $this->createMock( User::class );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturnMap( [
				[ $user, 'ipinfo', $permission ]
			] );

		$title = $this->createMock( Title::class );
		$title->method( 'isSpecial' )
			->willReturn( true );

		$out = $this->getOutputPage( [
			'user' => $user,
			'title' => $title,
		] );

		$handler = $this->getPopupHandler( [
			'PermissionManager' => $permissionManager,
		] );
		$handler->onBeforePageDisplay( $out, $this->createMock( Skin::class ) );
		$this->assertSame( $expected, in_array( 'ext.ipInfo', $out->getModules() ) );
	}

	public function provideOnBeforePageDisplayPermissions() {
		return [
			'displays with ipinfo right' => [ true, true ],
			'doesn\'t display with no ipinfo right' => [ false, false ],
		];
	}

	/**
	 * @dataProvider provideOnBeforePageDisplayPreferences
	 */
	public function testOnBeforePageDisplayPreferences( $preferences, $expected ) {
		$user = $this->createMock( User::class );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$map = array_map(
			static function ( $preference ) use ( $user ) {
				return [ $user, $preference, null, false, UserOptionsLookup::READ_NORMAL, true ];
			},
			// In case BetaFeatures is loaded
			array_merge( $preferences, [ 'ipinfo-beta-feature-enable' ] )
		);
		$userOptionsLookup->method( 'getOption' )
			->willReturnMap( $map );

		$title = $this->createMock( Title::class );
		$title->method( 'isSpecial' )
			->willReturn( true );

		$out = $this->getOutputPage( [
			'user' => $user,
			'title' => $title,
		] );

		$handler = $this->getPopupHandler( [
			'UserOptionsLookup' => $userOptionsLookup,
		] );
		$handler->onBeforePageDisplay( $out, $this->createMock( Skin::class ) );
		$this->assertSame( $expected, in_array( 'ext.ipInfo', $out->getModules() ) );
	}

	public function provideOnBeforePageDisplayPreferences() {
		return [
			'displays with ipinfo-use-agreement set' => [
				[
					'ipinfo-use-agreement',
				],
				true
			],
			'doesn\'t display without ipinfo-use-agreement' => [
				[],
				false
			],
		];
	}

}
