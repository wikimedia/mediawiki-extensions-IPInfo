<?php

namespace MediaWiki\IPInfo\Test\Integration\HookHandler;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\IPInfo\HookHandler\InfoboxHandler;
use MediaWiki\IPInfo\IPInfoPermissionManager;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;

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

	private IPInfoPermissionManager $ipInfoPermissionManager;

	private TempUserConfig $tempUserConfig;

	private SpecialPage $specialPage;

	private FauxRequest $request;

	/** @var (ExtensionRegistry&MockObject) */
	private ExtensionRegistry $extensionRegistry;

	private InfoboxHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		$this->tempUserConfig = $this->createMock( TempUserConfig::class );
		$this->request = RequestContext::getMain()->getRequest();
		$this->specialPage = $this->createMock( SpecialPage::class );
		$this->specialPage
			->method( 'getRequest' )
			->willReturn( $this->request );

		$this->extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$this->ipInfoPermissionManager = $this->createMock( IPInfoPermissionManager::class );

		$this->handler = new InfoboxHandler(
			$this->tempUserConfig,
			$this->extensionRegistry,
			$this->ipInfoPermissionManager
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
		$out->expects( $this->once() )
			->method( 'addHTML' )
			->willReturnCallback( function ( $html ) {
				$this->assertStringContainsString( 'ext-ipinfo-panel-layout', $html );
			} );

		$this->specialPage->method( 'getName' )
			->willReturn( 'Contributions' );
		$this->specialPage->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$this->specialPage->method( 'getOutput' )
			->willReturn( $out );

		$this->ipInfoPermissionManager->method( 'hasEnabledIPInfo' )
			->with( $accessingAuthority )
			->willReturn( true );

		$this->tempUserConfig->method( 'isTempName' )
			->with( $targetName )
			->willReturn( $targetIsTemp );

		$targetUser = $this->createMock( User::class );
		$targetUser->method( 'getName' )
			->willReturn( $targetName );

		$this->extensionRegistry
			->method( 'isLoaded' )
			->willReturnCallback(
				static fn ( $extensionName ) => $extensionName === 'BetaFeatures'
			);

		$this->handler->onSpecialContributionsBeforeMainOutput(
			1,
			$targetUser,
			$this->specialPage
		);
	}

	/**
	 * @dataProvider provideValidTargetsForIPContributions
	 */
	public function testShouldDisplayOnIPContributionsPageIfUserHasIpinfoRight(
		bool $shouldDisplayInfobox,
		string $targetName,
		bool $targetIsTemp,
		bool $targetIsAnon
	) {
		$accessingAuthority = self::getValidAccessingAuthority();

		$out = $this->createMock( OutputPage::class );
		$out->expects( $shouldDisplayInfobox ? $this->once() : $this->never() )
			->method( 'addModules' )
			->with( 'ext.ipInfo' );
		$out->expects( $shouldDisplayInfobox ? $this->once() : $this->never() )
			->method( 'addHTML' )
			->willReturnCallback( function ( $html ) {
				$this->assertStringContainsString( 'ext-ipinfo-panel-layout', $html );
			} );

		$specialPage = $this->createMock( SpecialPage::class );
		$specialPage->method( 'getName' )
			->willReturn( 'IPContributions' );
		$specialPage->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$specialPage->method( 'getOutput' )
			->willReturn( $out );

		$this->extensionRegistry
			->method( 'isLoaded' )
			->willReturnCallback(
				static fn ( $extensionName ) => $extensionName === 'BetaFeatures'
			);

		$this->ipInfoPermissionManager->method( 'hasEnabledIPInfo' )
			->with( $accessingAuthority )
			->willReturn( true );

		$this->tempUserConfig->method( 'isTempName' )
			->with( $targetName )
			->willReturn( $targetIsTemp );

		$targetUser = $this->createMock( User::class );
		$targetUser->method( 'getName' )
			->willReturn( $targetName );
		$targetUser->method( 'isAnon' )
			->willReturn( $targetIsAnon );

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
		$out->expects( $this->once() )
			->method( 'addHTML' )
			->willReturnCallback( function ( $html ) {
				$this->assertStringContainsString( 'ext-ipinfo-panel-layout', $html );
			} );

		$this->specialPage->method( 'getName' )
			->willReturn( 'DeletedContributions' );
		$this->specialPage->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$this->specialPage->method( 'getOutput' )
			->willReturn( $out );

		$this->extensionRegistry
			->method( 'isLoaded' )
			->willReturnCallback(
				static fn ( $extensionName ) => $extensionName === 'BetaFeatures'
			);

		$this->ipInfoPermissionManager->method( 'hasEnabledIPInfo' )
			->with( $accessingAuthority )
			->willReturn( true );

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

	public static function provideValidTargetsForIPContributions(): iterable {
		yield 'IPContributions show data for anonymous ("IP") users' => [
			'shouldDisplayInfobox' => true,
			'targetName' => '127.0.0.1',
			'targetIsTemp' => false,
			'targetIsAnon' => true,
		];
		yield 'IPContributions does not show data for temp users' => [
			'shouldDisplayInfobox' => false,
			'targetName' => '~2024-8',
			'targetIsTemp' => true,
			'targetIsAnon' => false,
		];
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
		$out->expects( $this->never() )
			->method( 'addHTML' );

		$this->specialPage->method( 'getName' )
			->willReturn( $specialPageName );
		$this->specialPage->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$this->specialPage->method( 'getOutput' )
			->willReturn( $out );

		$targetUser = $this->createMock( User::class );
		$targetUser->method( 'getName' )
			->willReturn( $targetUserName );

		$this->ipInfoPermissionManager->method( 'hasEnabledIPInfo' )
			->with( $accessingAuthority )
			->willReturn( false );

		$this->tempUserConfig->method( 'isTempName' )
			->with( $targetUserName )
			->willReturn( false );

		$this->extensionRegistry
			->method( 'isLoaded' )
			->willReturnCallback(
				static fn ( $extensionName ) => $extensionName === 'BetaFeatures'
			);

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
	 * @dataProvider provideDisplayConditionsForDeletedContributions
	 */
	public function testDisplayConditionsForDeletedContributions(
		bool $expected,
		string $specialPageName,
		Authority $accessingAuthority,
		string $targetUserName,
		bool $isTempName,
		int $offset,
		?string $direction
	) {
		$out = $this->createMock( OutputPage::class );
		$out->expects( $expected ? $this->once() : $this->never() )
			->method( 'addModules' );

		$this->specialPage->method( 'getName' )
			->willReturn( $specialPageName );
		$this->specialPage->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$this->specialPage->method( 'getOutput' )
			->willReturn( $out );

		$this->ipInfoPermissionManager->method( 'hasEnabledIPInfo' )
			->with( $accessingAuthority )
			->willReturn( $accessingAuthority->isAllowed( 'ipinfo' ) );

		$this->tempUserConfig
			->method( 'isTempName' )
			->with( $targetUserName )
			->willReturn( $isTempName );

		$this->extensionRegistry
			->method( 'isLoaded' )
			->willReturnCallback(
				static fn ( $extensionName ) => $extensionName === 'BetaFeatures'
			);

		$this->request->setVal( 'offset', $offset );
		$this->request->setVal( 'dir', $direction );

		$this->handler->onSpecialPageBeforeExecute(
			$this->specialPage,
			$targetUserName
		);
	}

	public static function provideDisplayConditionsForDeletedContributions(): iterable {
		yield 'incorrect special page' => [
			'expected' => false,
			'specialPageName' => 'AllPages',
			'accessingAuthority' => self::getValidAccessingAuthority(),
			'targetUserName' => '127.0.0.1',
			'isTempName' => false,
			'offset' => 0,
			'direction' => null
		];
		yield 'special page handled by other hook' => [
			'expected' => false,
			'specialPageName' => 'Contributions',
			'accessingAuthority' => self::getValidAccessingAuthority(),
			'targetUserName' => '127.0.0.1',
			'isTempName' => false,
			'offset' => 0,
			'direction' => null
		];
		yield 'named target user' => [
			'expected' => false,
			'specialPageName' => 'DeletedContributions',
			'accessingAuthority' => self::getValidAccessingAuthority(),
			'targetUserName' => 'TestUser',
			'isTempName' => false,
			'offset' => 0,
			'direction' => null
		];
		yield 'missing user rights' => [
			'expected' => false,
			'specialPageName' => 'DeletedContributions',
			'accessingAuthority' => new SimpleAuthority( new UserIdentityValue( 2, 'TestUser2' ), [] ),
			'targetUserName' => '127.0.0.1',
			'isTempName' => false,
			'offset' => 0,
			'direction' => null
		];
		yield 'IP address, first page' => [
			'expected' => true,
			'specialPageName' => 'DeletedContributions',
			'accessingAuthority' => self::getValidAccessingAuthority(),
			'targetUserName' => '127.0.0.1',
			'isTempName' => false,
			'offset' => 0,
			'direction' => null
		];
		yield 'IP address, non-first page' => [
			'expected' => true,
			'specialPageName' => 'DeletedContributions',
			'accessingAuthority' => self::getValidAccessingAuthority(),
			'targetUserName' => '127.0.0.1',
			'isTempName' => false,
			'offset' => 10,
			'direction' => null
		];
		yield 'IP address, first page, older first' => [
			'expected' => true,
			'specialPageName' => 'DeletedContributions',
			'accessingAuthority' => self::getValidAccessingAuthority(),
			'targetUserName' => '127.0.0.1',
			'isTempName' => false,
			'offset' => 0,
			'direction' => 'prev'
		];
		yield 'IP address, non-first page, older first' => [
			'expected' => true,
			'specialPageName' => 'DeletedContributions',
			'accessingAuthority' => self::getValidAccessingAuthority(),
			'targetUserName' => '127.0.0.1',
			'isTempName' => false,
			'offset' => 0,
			'direction' => 'prev'
		];
	}

	/**
	 * @dataProvider provideValidTargetsWithPaginationParams
	 */
	public function testIsDisplayedOnContributionsPageOnFirstPageOnly(
		bool $isDisplayed,
		string $pageName,
		string $targetName,
		bool $targetIsTemp,
		bool $targetIsAnon,
		?int $offset,
		?string $direction
	) {
		if ( $targetIsTemp && $targetIsAnon ) {
			$this->fail( 'Bad test case: A user cannot be both temp and anon' );
		}

		$accessingAuthority = self::getValidAccessingAuthority();

		$out = $this->createMock( OutputPage::class );
		$out->expects( $isDisplayed ? $this->once() : $this->never() )
			->method( 'addModules' )
			->with( 'ext.ipInfo' );

		$this->specialPage->method( 'getName' )
			->willReturn( $pageName );
		$this->specialPage->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$this->specialPage->method( 'getOutput' )
			->willReturn( $out );

		$this->ipInfoPermissionManager->method( 'hasEnabledIPInfo' )
			->with( $accessingAuthority )
			->willReturn( true );

		$this->tempUserConfig->method( 'isTempName' )
			->with( $targetName )
			->willReturn( $targetIsTemp );

		$targetUser = $this->createMock( User::class );
		$targetUser->method( 'getName' )
			->willReturn( $targetName );
		$targetUser->method( 'isAnon' )
			->willReturn( $targetIsAnon );

		$this->extensionRegistry
			->method( 'isLoaded' )
			->willReturnCallback(
				static fn ( $extensionName ) => $extensionName === 'BetaFeatures'
			);

		$this->request->setVal( 'offset', $offset );
		$this->request->setVal( 'dir', $direction );

		$this->handler->onSpecialContributionsBeforeMainOutput(
			1,
			$targetUser,
			$this->specialPage
		);
	}

	public static function provideValidTargetsWithPaginationParams(): iterable {
		// IP users (i.e. anonymous) should show the infobox in all pages (their
		// name itself is an IP, therefore does not change across pages)
		yield 'Contributions: IP user, on the first page, newer first' => [
			'isDisplayed' => true,
			'pageName' => 'Contributions',
			'targetName' => '127.0.0.1',
			'targetIsTemp' => false,
			'targetIsAnon' => true,
			'offset' => 0,
			'direction' => null
		];
		yield 'Contributions: IP user, not on the first page, newer first' => [
			'isDisplayed' => true,
			'pageName' => 'Contributions',
			'targetName' => '127.0.0.1',
			'targetIsTemp' => false,
			'targetIsAnon' => true,
			'offset' => 100,
			'direction' => null
		];
		yield 'Contributions: IP user, on the first page, older first' => [
			'isDisplayed' => true,
			'pageName' => 'Contributions',
			'targetName' => '127.0.0.1',
			'targetIsTemp' => false,
			'targetIsAnon' => true,
			'offset' => 0,
			'direction' => 'prev'
		];
		yield 'Contributions: IP user, not on the first page, older first' => [
			'isDisplayed' => true,
			'pageName' => 'Contributions',
			'targetName' => '127.0.0.1',
			'targetIsTemp' => false,
			'targetIsAnon' => true,
			'offset' => 100,
			'direction' => 'prev'
		];
		// Temp users may change IP by reconnecting (switching Wi-Fi networks
		// etc. without logging out explicitly): We can't guarantee their IP is
		// consistent across contributions, therefore the info is shown only in
		// the first page.
		yield 'Contributions: Temp user, on the first page, newer first' => [
			'isDisplayed' => true,
			'pageName' => 'Contributions',
			'targetName' => '~2024-8',
			'targetIsTemp' => true,
			'targetIsAnon' => false,
			'offset' => 0,
			'direction' => null
		];
		yield 'Contributions: Temp user, not on the first page, newer first' => [
			'isDisplayed' => false,
			'pageName' => 'Contributions',
			'targetName' => '~2024-8',
			'targetIsTemp' => true,
			'targetIsAnon' => false,
			'offset' => 100,
			'direction' => null
		];
		yield 'Contributions: Temp user, on the first page, older first' => [
			'isDisplayed' => false,
			'pageName' => 'Contributions',
			'targetName' => '~2024-8',
			'targetIsTemp' => true,
			'targetIsAnon' => false,
			'offset' => 0,
			'direction' => 'prev'
		];
		yield 'Contributions: Temp user, not on the first page, older first' => [
			'isDisplayed' => false,
			'pageName' => 'Contributions',
			'targetName' => '~2024-8',
			'targetIsTemp' => true,
			'targetIsAnon' => false,
			'offset' => 100,
			'direction' => 'prev'
		];
		// IPContributions is meant to always take an IP as its parameter, so
		// the infobox is always shown regardless of the requested page number
		// and direction.
		yield 'IPContributions: Valid IP, first page' => [
			'isDisplayed' => true,
			'pageName' => 'IPContributions',
			'targetName' => '127.0.0.1',
			'targetIsTemp' => false,
			'targetIsAnon' => true,
			'offset' => 0,
			'direction' => null
		];
		yield 'IPContributions: Valid IP, not first page' => [
			'isDisplayed' => true,
			'pageName' => 'IPContributions',
			'targetName' => '127.0.0.1',
			'targetIsTemp' => false,
			'targetIsAnon' => true,
			'offset' => 100,
			'direction' => null
		];
		yield 'IPContributions: Valid IP, first page, older first' => [
			'isDisplayed' => true,
			'pageName' => 'IPContributions',
			'targetName' => '127.0.0.1',
			'targetIsTemp' => false,
			'targetIsAnon' => true,
			'offset' => 0,
			'direction' => 'prev'
		];
		yield 'IPContributions: Valid IP, non-first page, older first' => [
			'isDisplayed' => true,
			'pageName' => 'IPContributions',
			'targetName' => '127.0.0.1',
			'targetIsTemp' => false,
			'targetIsAnon' => true,
			'offset' => 100,
			'direction' => 'prev'
		];
		// The URL may carry a wrong, non-IP parameter (bad IPs, TempUser
		// names...), and we should not try to load the IP info in that case
		// (the page already shows an error message in those cases). This means
		// we need to check if the user is anonymous (i.e. an "IP user").
		yield 'IPContributions: Invalid IP, non-temp user' => [
			'isDisplayed' => false,
			'pageName' => 'IPContributions',
			'targetName' => '300.0.0.1',
			'targetIsTemp' => false,
			'targetIsNamed' => false,
			'offset' => 0,
			'direction' => 'prev'
		];
		yield 'IPContributions: Bad request targeting a temp user' => [
			'isDisplayed' => false,
			'pageName' => 'IPContributions',
			'targetName' => '~2024-8',
			'targetIsTemp' => true,
			'targetIsNamed' => false,
			'offset' => 0,
			'direction' => 'prev'
		];
	}

	/**
	 * @dataProvider provideSkipsLinkingToBetaFeaturesIfConditionsNotMet
	 */
	public function testSkipsLinkingToBetaFeaturesIfConditionsNotMet(
		bool $expectsToAddInfoBox,
		bool $expectsBeingBetaFeature,
		string $target,
		bool $tempUsersAreKnown,
		bool $ipInfoEnabled,
		array $extensionsLoaded
	) {
		$accessingAuthority = self::getValidAccessingAuthority();
		$outputPage = new OutputPage(
			new DerivativeContext( RequestContext::getMain() )
		);

		$this->specialPage
			->method( 'getName' )
			->willReturn( 'Contributions' );
		$this->specialPage
			->method( 'getAuthority' )
			->willReturn( $accessingAuthority );
		$this->specialPage
			->method( 'getOutput' )
			->willReturn( $outputPage );

		$targetUser = $this->createMock( User::class );
		$targetUser
			->method( 'getName' )
			->willReturn( $target );

		$this->ipInfoPermissionManager
			->method( 'hasEnabledIPInfo' )
			->with( $accessingAuthority )
			->willReturn( $ipInfoEnabled );

		$this->tempUserConfig
			->method( 'isTempName' )
			->with( $target )
			->willReturn( false );
		$this->tempUserConfig
			->method( 'isKnown' )
			->willReturn( $tempUsersAreKnown );

		$this->extensionRegistry
			->method( 'isLoaded' )
			->willReturnCallback(
				static fn ( $extensionName ) => in_array(
					$extensionName,
					$extensionsLoaded
				)
			);

		$this->handler->onSpecialContributionsBeforeMainOutput(
			1,
			$targetUser,
			$this->specialPage
		);

		if ( $expectsToAddInfoBox ) {
			$this->assertArrayContains(
				[ 'ext.ipInfo' ],
				$outputPage->getModules()
			);
			$this->assertArrayContains(
				[ 'ext.ipInfo.styles' ],
				$outputPage->getModuleStyles()
			);
			$this->assertArrayHasKey(
				'wgIPInfoIsABetaFeature',
				$outputPage->getJsConfigVars()
			);
			$this->assertEquals(
				$expectsBeingBetaFeature,
				$outputPage->getJsConfigVars()[ 'wgIPInfoIsABetaFeature' ]
			);
		} else {
			$this->assertNotContains(
				'ext.ipInfo',
				$outputPage->getModules()
			);
			$this->assertNotContains(
				'ext.ipInfo.styles',
				$outputPage->getModuleStyles()
			);
			$this->assertArrayNotHasKey(
				'wgIPInfoIsABetaFeature',
				$outputPage->getJsConfigVars()
			);
		}
	}

	public static function provideSkipsLinkingToBetaFeaturesIfConditionsNotMet(): iterable {
		// BetaFeatures not loaded: Skips adding the link as there's nothing to link to
		yield 'Is not beta: BetaFeatures not loaded, temp users not known' => [
			'expectsToAddInfoBox' => true,
			'expectsBeingBetaFeature' => false,
			'target' => '127.0.0.1',
			'tempUsersAreKnown' => false,
			'ipInfoEnabled' => true,
			'extensionsLoaded' => [],
		];
		yield 'Is not beta: BetaFeatures not loaded, temp users known' => [
			'expectsToAddInfoBox' => true,
			'expectsBeingBetaFeature' => false,
			'target' => '127.0.0.1',
			'tempUsersAreKnown' => true,
			'ipInfoEnabled' => true,
			'extensionsLoaded' => [],
		];

		// BetaFeatures loaded: Link added only if Temp Users are known
		yield 'Is beta: BetaFeatures loaded, temp users not known' => [
			'expectsToAddInfoBox' => true,
			'expectsBeingBetaFeature' => true,
			'target' => '127.0.0.1',
			'tempUsersAreKnown' => false,
			'ipInfoEnabled' => true,
			'extensionsLoaded' => [ 'BetaFeatures' ],
		];
		yield 'Is not beta: BetaFeatures loaded, temp users known' => [
			'expectsToAddInfoBox' => true,
			'expectsBeingBetaFeature' => false,
			'target' => '127.0.0.1',
			'tempUsersAreKnown' => true,
			'ipInfoEnabled' => true,
			'extensionsLoaded' => [ 'BetaFeatures' ],
		];

		// Conditions not leading to adding the infobox also make skipping
		// the wgIPInfoIsABetaFeature Javascript variable.
		yield 'Disabling IPInfo skips adding the js config for the link' => [
			'expectsToAddInfoBox' => false,
			'expectsBeingBetaFeature' => false,
			'target' => '127.0.0.1',
			'tempUsersAreKnown' => true,
			'ipInfoEnabled' => false,
			'extensionsLoaded' => [ 'BetaFeatures' ],
		];
		yield 'Neither the link nor the js config are added for non-temp accounts' => [
			'expectsToAddInfoBox' => false,
			'expectsBeingBetaFeature' => false,
			'target' => 'Username',
			'tempUsersAreKnown' => true,
			'ipInfoEnabled' => true,
			'extensionsLoaded' => [ 'BetaFeatures' ],
		];
	}
}
