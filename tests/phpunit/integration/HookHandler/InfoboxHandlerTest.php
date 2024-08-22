<?php

namespace MediaWiki\IPInfo\Test\Integration\HookHandler;

use MediaWiki\IPInfo\HookHandler\InfoboxHandler;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

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

	private UserOptionsLookup $userOptionsLookup;

	private InfoboxHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		$this->userOptionsLookup = $this->createMock( UserOptionsLookup::class );

		$this->handler = new InfoboxHandler(
			$this->userOptionsLookup
		);
	}

	private static function getValidAccessingAuthority(): Authority {
		return new SimpleAuthority(
			new UserIdentityValue( 1, 'TestUser' ),
			[ 'ipinfo' ]
		);
	}

	public function testShouldDisplayOnContributionsPageIfUserHasIpinfoRight() {
		$accessingAuthority = self::getValidAccessingAuthority();

		$out = $this->createMock( OutputPage::class );
		$out->expects( $this->once() )
			->method( 'addModules' )
			->with( 'ext.ipInfo' );

		$specialPage = $this->createMock( SpecialPage::class );
		$specialPage->method( 'getName' )
			->willReturn( 'Contributions' );
		$specialPage->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$specialPage->method( 'getOutput' )
			->willReturn( $out );

		$this->userOptionsLookup->method( 'getOption' )
			->with( $accessingAuthority->getUser(), 'ipinfo-beta-feature-enable' )
			->willReturn( '1' );

		$targetUser = $this->createMock( User::class );
		$targetUser->method( 'getName' )
			->willReturn( '127.0.0.1' );

		$this->handler->onSpecialContributionsBeforeMainOutput(
			1,
			$targetUser,
			$specialPage
		);
	}

	public function testShouldDisplayOnDeletedContributionsPageIfUserHasIpinfoRight() {
		$accessingAuthority = self::getValidAccessingAuthority();

		$out = $this->createMock( OutputPage::class );
		$out->expects( $this->once() )
			->method( 'addModules' )
			->with( 'ext.ipInfo' );

		$specialPage = $this->createMock( SpecialPage::class );
		$specialPage->method( 'getName' )
			->willReturn( 'DeletedContributions' );
		$specialPage->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$specialPage->method( 'getOutput' )
			->willReturn( $out );

		$this->userOptionsLookup->method( 'getOption' )
			->with( $accessingAuthority->getUser(), 'ipinfo-beta-feature-enable' )
			->willReturn( '1' );

		$this->handler->onSpecialPageBeforeExecute(
			$specialPage,
			'127.0.0.1'
		);
	}

	/**
	 * @dataProvider provideContributionsErrorCases
	 */
	public function testShouldNotDisplayOnNonContributionsPageOrIfPermissionsAreMissing(
		string $specialPageName,
		Authority $accessingAuthority,
		string $targetUserName
	) {
		$out = $this->createMock( OutputPage::class );
		$out->expects( $this->never() )
			->method( 'addModules' );

		$specialPage = $this->createMock( SpecialPage::class );
		$specialPage->method( 'getName' )
			->willReturn( $specialPageName );
		$specialPage->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$specialPage->method( 'getOutput' )
			->willReturn( $out );

		$targetUser = $this->createMock( User::class );
		$targetUser->method( 'getName' )
			->willReturn( $targetUserName );

		$this->userOptionsLookup->method( 'getOption' )
			->with( $accessingAuthority->getUser(), 'ipinfo-beta-feature-enable' )
			->willReturn( '1' );

		$this->handler->onSpecialContributionsBeforeMainOutput(
			1,
			$targetUser,
			$specialPage
		);
	}

	public static function provideContributionsErrorCases(): iterable {
		yield 'incorrect special page' => [
			'AllPages',
			self::getValidAccessingAuthority(),
			'127.0.0.1'
		];
		yield 'special page handled by other hook' => [
			'DeletedContributions',
			self::getValidAccessingAuthority(),
			'127.0.0.1'
		];
		yield 'named target user' => [
			'DeletedContributions',
			self::getValidAccessingAuthority(),
			'TestUser'
		];
		yield 'missing user rights' => [
			'Contributions',
			new SimpleAuthority( new UserIdentityValue( 2, 'TestUser2' ), [] ),
			'127.0.0.1'
		];
	}

	/**
	 * @dataProvider provideDeletedContributionsErrorCases
	 */
	public function testShouldNotDisplayOnNonDeletedContributionsPageOrIfPermissionsAreMissing(
		string $specialPageName,
		Authority $accessingAuthority,
		string $targetUserName
	) {
		$out = $this->createMock( OutputPage::class );
		$out->expects( $this->never() )
			->method( 'addModules' );

		$specialPage = $this->createMock( SpecialPage::class );
		$specialPage->method( 'getName' )
			->willReturn( $specialPageName );
		$specialPage->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$specialPage->method( 'getOutput' )
			->willReturn( $out );

		$this->userOptionsLookup->method( 'getOption' )
			->with( $accessingAuthority->getUser(), 'ipinfo-beta-feature-enable' )
			->willReturn( '1' );

		$this->handler->onSpecialPageBeforeExecute(
			$specialPage,
			$targetUserName
		);
	}

	public static function provideDeletedContributionsErrorCases(): iterable {
		yield 'incorrect special page' => [
			'AllPages',
			self::getValidAccessingAuthority(),
			'127.0.0.1'
		];
		yield 'special page handled by other hook' => [
			'Contributions',
			self::getValidAccessingAuthority(),
			'127.0.0.1'
		];
		yield 'named target user' => [
			'DeletedContributions',
			self::getValidAccessingAuthority(),
			'TestUser'
		];
		yield 'missing user rights' => [
			'Contributions',
			new SimpleAuthority( new UserIdentityValue( 2, 'TestUser2' ), [] ),
			'127.0.0.1'
		];
	}

}
