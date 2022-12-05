<?php

namespace MediaWiki\IPInfo\Test\Integration\HookHandler;

use MediaWiki\IPInfo\HookHandler\InfoboxHandler;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiIntegrationTestCase;
use OutputPage;
use SpecialPage;
use User;

/**
 * TODO: Make this into a unit test once T268177 is resolved.
 * InfoboxHandler calls MobileContext::shouldDisplayMobileView, which
 * uses a global config. Rather than mocking the layers that lead up
 * to this (if it's even possible), this is an integration test.
 *
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\HookHandler\InfoboxHandler
 */
class InfoboxHandlerTest extends MediaWikiIntegrationTestCase {

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

	private function getInfoboxHandler( $overrides = null ) {
		return new InfoboxHandler(
			$overrides[ 'PermissionManager' ] ?? $this->getPermissionManager(),
			$overrides[ 'UserOptionsLookup' ] ?? $this->getUserOptionsLookup()
		);
	}

	private function getOutputPage( $overrides = null ) {
		return $this->getMockBuilder( OutputPage::class )
			->disableOriginalConstructor()
			->setMethodsExcept( [
				'addModules',
				'getModules',
			] )
			->getMock();
	}

	private function getIpUser() {
		$user = $this->createMock( User::class );
		$user->method( 'getName' )
			->willReturn( '127.0.0.1' );
		return $user;
	}

	/**
	 * @dataProvider provideOnSpecialContributionsBeforeMainOutputTitles
	 */
	public function testOnSpecialContributionsBeforeMainOutputTitles( $name, $expected ) {
		$out = $this->getOutputPage();

		$specialPage = $this->createMock( SpecialPage::class );
		$specialPage->method( 'getName' )
			->willReturn( $name );
		$specialPage->method( 'getUser' )
			->willReturn( $this->createMock( User::class ) );
		$specialPage->method( 'getOutput' )
			->willReturn( $out );

		$handler = $this->getInfoboxHandler();
		$handler->onSpecialContributionsBeforeMainOutput(
			1,
			$this->getIpUser(),
			$specialPage
		);
		$this->assertSame( $expected, in_array( 'ext.ipInfo', $out->getModules() ) );
	}

	public function provideOnSpecialContributionsBeforeMainOutputTitles() {
		return [
			'displays on Special:Contributions' => [ 'Contributions', true ],
			'doesn\'t display on Special:AllPages' => [ 'AllPages', false ],
		];
	}

	/**
	 * @dataProvider provideOnSpecialContributionsBeforeMainOutputPermissions
	 */
	public function testOnSpecialContributionsBeforeMainOutputPermissions(
		$permission,
		$expected
	) {
		$accessingUser = $this->createMock( User::class );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturnMap( [
				[ $accessingUser, 'ipinfo', $permission ]
			] );

		$out = $this->getOutputPage();

		$specialPage = $this->createMock( SpecialPage::class );
		$specialPage->method( 'getName' )
			->willReturn( 'Contributions' );
		$specialPage->method( 'getUser' )
			->willReturn( $accessingUser );
		$specialPage->method( 'getOutput' )
			->willReturn( $out );

		$handler = $this->getInfoboxHandler( [
			'PermissionManager' => $permissionManager,
		] );
		$handler->onSpecialContributionsBeforeMainOutput(
			1,
			$this->getIpUser(),
			$specialPage
		);
		$this->assertSame( $expected, in_array( 'ext.ipInfo', $out->getModules() ) );
	}

	public function provideOnSpecialContributionsBeforeMainOutputPermissions() {
		return [
			'displays with ipinfo right' => [ true, true ],
			'doesn\'t display with no ipinfo right' => [ false, false ],
		];
	}

	/**
	 * @dataProvider provideOnSpecialContributionsBeforeMainOutputPreferences
	 */
	public function testOnSpecialContributionsBeforeMainOutputPreferences(
		$preferences,
		$expected
	) {
		$accessingUser = $this->createMock( User::class );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$map = [];
		$map = array_map(
			static function ( $preference ) use ( $accessingUser ) {
				return [
					$accessingUser,
					$preference,
					null,
					false,
					UserOptionsLookup::READ_NORMAL,
					true
				];
			},
			// In case BetaFeatures is loaded
			array_merge( $preferences, [ 'ipinfo-beta-feature-enable' ] )
		);
		$userOptionsLookup->method( 'getOption' )
			->willReturnMap( $map );

		$out = $this->getOutputPage();

		$specialPage = $this->createMock( SpecialPage::class );
		$specialPage->method( 'getName' )
			->willReturn( 'Contributions' );
		$specialPage->method( 'getUser' )
			->willReturn( $accessingUser );
		$specialPage->method( 'getOutput' )
			->willReturn( $out );

		$handler = $this->getInfoboxHandler( [
			'UserOptionsLookup' => $userOptionsLookup,
		] );
		$handler->onSpecialContributionsBeforeMainOutput(
			1,
			$this->getIpUser(),
			$specialPage
		);
		$this->assertSame( $expected, in_array( 'ext.ipInfo', $out->getModules() ) );
	}

	public function provideOnSpecialContributionsBeforeMainOutputPreferences() {
		return [
			'Display with no preferences set' => [
				[],
				true
			],
		];
	}

}
