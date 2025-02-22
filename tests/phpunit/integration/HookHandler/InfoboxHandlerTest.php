<?php

namespace MediaWiki\IPInfo\Test\Integration\HookHandler;

use MediaWiki\Context\RequestContext;
use MediaWiki\IPInfo\HookHandler\InfoboxHandler;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;
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

	private TempUserConfig $tempUserConfig;

	private SpecialPage $specialPage;

	private FauxRequest $request;

	private InfoboxHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		$this->userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$this->tempUserConfig = $this->createMock( TempUserConfig::class );
		$this->request = RequestContext::getMain()->getRequest();
		$this->specialPage = $this->createMock( SpecialPage::class );
		$this->specialPage
			->method( 'getRequest' )
			->willReturn( $this->request );

		$this->handler = new InfoboxHandler(
			$this->userOptionsLookup,
			$this->tempUserConfig
		);
	}

	private static function getValidAccessingAuthority(): Authority {
		return new SimpleAuthority(
			new UserIdentityValue( 1, 'TestUser' ),
			[ 'ipinfo' ]
		);
	}

	/**
	 * @dataProvider provideValidTargets
	 */
	public function testShouldDisplayOnContributionsPageIfUserHasIpinfoRight(
		string $targetName,
		bool $targetIsTemp
	) {
		$accessingAuthority = self::getValidAccessingAuthority();

		$out = $this->createMock( OutputPage::class );
		$out->expects( $this->once() )
			->method( 'addModules' )
			->with( 'ext.ipInfo' );

		$this->specialPage->method( 'getName' )
			->willReturn( 'Contributions' );
		$this->specialPage->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$this->specialPage->method( 'getOutput' )
			->willReturn( $out );

		$this->userOptionsLookup->method( 'getOption' )
			->with( $accessingAuthority->getUser(), 'ipinfo-beta-feature-enable' )
			->willReturn( '1' );

		$this->tempUserConfig->method( 'isTempName' )
			->with( $targetName )
			->willReturn( $targetIsTemp );

		$targetUser = $this->createMock( User::class );
		$targetUser->method( 'getName' )
			->willReturn( $targetName );

		$this->handler->onSpecialContributionsBeforeMainOutput(
			1,
			$targetUser,
			$this->specialPage
		);
	}

	/**
	 * @dataProvider provideValidTargets
	 */
	public function testShouldDisplayOnIPContributionsPageIfUserHasIpinfoRight(
		string $targetName,
		bool $targetIsTemp
	) {
		$accessingAuthority = self::getValidAccessingAuthority();

		$out = $this->createMock( OutputPage::class );
		$out->expects( $this->once() )
			->method( 'addModules' )
			->with( 'ext.ipInfo' );

		$specialPage = $this->createMock( SpecialPage::class );
		$specialPage->method( 'getName' )
			->willReturn( 'IPContributions' );
		$specialPage->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$specialPage->method( 'getOutput' )
			->willReturn( $out );

		$this->userOptionsLookup->method( 'getOption' )
			->with( $accessingAuthority->getUser(), 'ipinfo-beta-feature-enable' )
			->willReturn( '1' );

		$this->tempUserConfig->method( 'isTempName' )
			->with( $targetName )
			->willReturn( $targetIsTemp );

		$targetUser = $this->createMock( User::class );
		$targetUser->method( 'getName' )
			->willReturn( $targetName );

		$this->handler->onSpecialContributionsBeforeMainOutput(
			1,
			$targetUser,
			$specialPage
		);
	}

	/**
	 * @dataProvider provideValidTargets
	 */
	public function testShouldDisplayOnDeletedContributionsPageIfUserHasIpinfoRight(
		string $targetName,
		bool $targetIsTemp
	) {
		$accessingAuthority = self::getValidAccessingAuthority();

		$out = $this->createMock( OutputPage::class );
		$out->expects( $this->once() )
			->method( 'addModules' )
			->with( 'ext.ipInfo' );

		$this->specialPage->method( 'getName' )
			->willReturn( 'DeletedContributions' );
		$this->specialPage->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$this->specialPage->method( 'getOutput' )
			->willReturn( $out );

		$this->userOptionsLookup->method( 'getOption' )
			->with( $accessingAuthority->getUser(), 'ipinfo-beta-feature-enable' )
			->willReturn( '1' );

		$this->tempUserConfig->method( 'isTempName' )
			->with( $targetName )
			->willReturn( $targetIsTemp );

		$this->handler->onSpecialPageBeforeExecute(
			$this->specialPage,
			$targetName
		);
	}

	public static function provideValidTargets(): iterable {
		yield 'anonymous user' => [ '127.0.0.1', false ];
		yield 'temporary user' => [ '~2024-8', true ];
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

		$this->specialPage->method( 'getName' )
			->willReturn( $specialPageName );
		$this->specialPage->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$this->specialPage->method( 'getOutput' )
			->willReturn( $out );

		$targetUser = $this->createMock( User::class );
		$targetUser->method( 'getName' )
			->willReturn( $targetUserName );

		$this->userOptionsLookup->method( 'getOption' )
			->with( $accessingAuthority->getUser(), 'ipinfo-beta-feature-enable' )
			->willReturn( '1' );

		$this->tempUserConfig->method( 'isTempName' )
			->with( $targetUserName )
			->willReturn( false );

		$this->handler->onSpecialContributionsBeforeMainOutput(
			1,
			$targetUser,
			$this->specialPage
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

		$this->specialPage->method( 'getName' )
			->willReturn( $specialPageName );
		$this->specialPage->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$this->specialPage->method( 'getOutput' )
			->willReturn( $out );

		$this->userOptionsLookup->method( 'getOption' )
			->with( $accessingAuthority->getUser(), 'ipinfo-beta-feature-enable' )
			->willReturn( '1' );

		$this->tempUserConfig->method( 'isTempName' )
			->with( $targetUserName )
			->willReturn( false );

		$this->handler->onSpecialPageBeforeExecute(
			$this->specialPage,
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

	/**
	 * @dataProvider provideValidTargetsWithPaginationParams
	 */
	public function testIsDisplayedOnContributionsPageOnFirstPageOnly(
		bool $isDisplayed,
		string $targetName,
		bool $targetIsTemp,
		?int $offset,
		?string $direction
	) {
		$accessingAuthority = self::getValidAccessingAuthority();

		$out = $this->createMock( OutputPage::class );
		$out->expects( $isDisplayed ? $this->once() : $this->never() )
			->method( 'addModules' )
			->with( 'ext.ipInfo' );

		$this->specialPage->method( 'getName' )
			->willReturn( 'Contributions' );
		$this->specialPage->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$this->specialPage->method( 'getOutput' )
			->willReturn( $out );

		$this->userOptionsLookup->method( 'getOption' )
			->with( $accessingAuthority->getUser(), 'ipinfo-beta-feature-enable' )
			->willReturn( '1' );

		$this->tempUserConfig->method( 'isTempName' )
			->with( $targetName )
			->willReturn( $targetIsTemp );

		$targetUser = $this->createMock( User::class );
		$targetUser->method( 'getName' )
			->willReturn( $targetName );

		$this->request->setVal( 'offset', $offset );
		$this->request->setVal( 'dir', $direction );

		$this->handler->onSpecialContributionsBeforeMainOutput(
			1,
			$targetUser,
			$this->specialPage
		);
	}

	public static function provideValidTargetsWithPaginationParams(): iterable {
		yield 'IP user, on the first page, newer first' => [
			'isDisplayed' => true,
			'targetName' => '127.0.0.1',
			'targetIsTemp' => false,
			'offset' => 0,
			'direction' => null
		];
		yield 'IP user, not on the first page, newer first' => [
			'isDisplayed' => false,
			'targetName' => '127.0.0.1',
			'targetIsTemp' => false,
			'offset' => 100,
			'direction' => null
		];
		yield 'IP user, on the first page, older first' => [
			'isDisplayed' => false,
			'targetName' => '127.0.0.1',
			'targetIsTemp' => false,
			'offset' => 0,
			'direction' => 'prev'
		];
		yield 'IP user, not on the first page, older first' => [
			'isDisplayed' => false,
			'targetName' => '127.0.0.1',
			'targetIsTemp' => false,
			'offset' => 100,
			'direction' => 'prev'
		];
		yield 'temporary user, on the first page, newer first' => [
			'isDisplayed' => true,
			'targetName' => '~2024-8',
			'targetIsTemp' => true,
			'offset' => 0,
			'direction' => null
		];
		yield 'temporary user, not on the first page, newer first' => [
			'isDisplayed' => false,
			'targetName' => '~2024-8',
			'targetIsTemp' => true,
			'offset' => 100,
			'direction' => null
		];
		yield 'temporary user, on the first page, older first' => [
			'isDisplayed' => false,
			'targetName' => '~2024-8',
			'targetIsTemp' => true,
			'offset' => 0,
			'direction' => 'prev'
		];
		yield 'temporary user, not on the first page, older first' => [
			'isDisplayed' => false,
			'targetName' => '~2024-8',
			'targetIsTemp' => true,
			'offset' => 100,
			'direction' => 'prev'
		];
	}
}
