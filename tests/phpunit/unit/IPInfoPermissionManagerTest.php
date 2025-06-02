<?php
namespace MediaWiki\IPInfo\Test\Unit;

use MediaWiki\Block\Block;
use MediaWiki\IPInfo\HookHandler\PreferencesHandler;
use MediaWiki\IPInfo\IPInfoPermissionManager;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @covers \MediaWiki\IPInfo\IPInfoPermissionManager
 */
class IPInfoPermissionManagerTest extends MediaWikiUnitTestCase {
	private ExtensionRegistry $extensionRegistry;
	private UserOptionsLookup $userOptionsLookup;
	private TempUserConfig $tempUserConfig;

	private IPInfoPermissionManager $ipInfoPermissionManager;

	protected function setUp(): void {
		parent::setUp();

		$this->extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$this->userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$this->tempUserConfig = $this->createMock( TempUserConfig::class );

		$this->ipInfoPermissionManager = new IPInfoPermissionManager(
			$this->extensionRegistry,
			$this->userOptionsLookup,
			$this->tempUserConfig
		);
	}

	/**
	 * @dataProvider provideHasEnabledIPInfo
	 */
	public function testHasEnabledIPInfo(
		array $permissions,
		bool $areBetaFeaturesLoaded,
		bool $areTemporaryAccountsKnown,
		bool $hasEnabledBetaFeature,
		bool $expected
	): void {
		$authority = new SimpleAuthority(
			new UserIdentityValue( 1, 'TestUser' ),
			$permissions
		);

		$this->extensionRegistry->method( 'isLoaded' )
			->with( 'BetaFeatures' )
			->willReturn( $areBetaFeaturesLoaded );

		$this->tempUserConfig->method( 'isKnown' )
			->willReturn( $areTemporaryAccountsKnown );

		$this->userOptionsLookup->method( 'getBoolOption' )
			->with( $authority->getUser(), 'ipinfo-beta-feature-enable' )
			->willReturn( $hasEnabledBetaFeature );

		$hasEnabledIPInfo = $this->ipInfoPermissionManager->hasEnabledIPInfo( $authority );

		$this->assertSame( $expected, $hasEnabledIPInfo );
	}

	public static function provideHasEnabledIPInfo(): iterable {
		yield 'user without correct permissions with BetaFeature toggle enabled' => [
			'permissions' => [],
			'areBetaFeaturesLoaded' => true,
			'areTemporaryAccountsKnown' => false,
			'hasEnabledBetaFeature' => true,
			'expected' => false,
		];

		yield 'user without correct permissions on wiki with temporary accounts' => [
			'permissions' => [],
			'areBetaFeaturesLoaded' => true,
			'areTemporaryAccountsKnown' => true,
			'hasEnabledBetaFeature' => false,
			'expected' => false,
		];

		yield 'user with correct permissions with BetaFeature toggle disabled' => [
			'permissions' => [ 'ipinfo' ],
			'areBetaFeaturesLoaded' => true,
			'areTemporaryAccountsKnown' => false,
			'hasEnabledBetaFeature' => false,
			'expected' => false,
		];

		yield 'user with correct permissions with BetaFeature toggle enabled' => [
			'permissions' => [ 'ipinfo' ],
			'areBetaFeaturesLoaded' => true,
			'areTemporaryAccountsKnown' => false,
			'hasEnabledBetaFeature' => true,
			'expected' => true,
		];

		yield 'user with correct permissions on wiki with temporary accounts' => [
			'permissions' => [ 'ipinfo' ],
			'areBetaFeaturesLoaded' => true,
			'areTemporaryAccountsKnown' => true,
			'hasEnabledBetaFeature' => false,
			'expected' => true,
		];

		yield 'user with correct permissions on wiki without BetaFeatures' => [
			'permissions' => [ 'ipinfo' ],
			'areBetaFeaturesLoaded' => false,
			'areTemporaryAccountsKnown' => false,
			'hasEnabledBetaFeature' => false,
			'expected' => true,
		];
	}

	/**
	 * @dataProvider provideCanViewIPInfo
	 */
	public function testCanViewIPInfo(
		array $permissions,
		bool $areBetaFeaturesLoaded,
		bool $areTemporaryAccountsKnown,
		bool $hasEnabledBetaFeature,
		bool $hasAcceptedDataUseAgreement,
		bool $isBlockedSitewide,
		bool $expected
	): void {
		$user = new UserIdentityValue( 1, 'TestUser' );
		$block = null;

		if ( $isBlockedSitewide ) {
			$block = $this->createMock( Block::class );
			$block->method( 'isSitewide' )
				->willReturn( true );
		}

		$authority = $this->createMock( Authority::class );
		$authority->method( 'isAllowed' )
			->with( 'ipinfo' )
			->willReturnCallback( static fn ( $permission ) => in_array( $permission, $permissions ) );
		$authority->method( 'getUser' )
			->willReturn( $user );
		$authority->method( 'getBlock' )
			->willReturn( $block );

		$this->extensionRegistry->method( 'isLoaded' )
			->with( 'BetaFeatures' )
			->willReturn( $areBetaFeaturesLoaded );

		$this->tempUserConfig->method( 'isKnown' )
			->willReturn( $areTemporaryAccountsKnown );

		$this->userOptionsLookup->method( 'getBoolOption' )
			->willReturnMap( [
				[ $user, 'ipinfo-beta-feature-enable', IDBAccessObject::READ_NORMAL, $hasEnabledBetaFeature ],
				[
					$user, PreferencesHandler::IPINFO_USE_AGREEMENT, IDBAccessObject::READ_NORMAL,
					$hasAcceptedDataUseAgreement,
				],
			] );

		$canView = $this->ipInfoPermissionManager->canViewIPInfo( $authority );

		$this->assertSame( $expected, $canView );
	}

	public static function provideCanViewIPInfo(): iterable {
		yield 'user with correct permissions with BetaFeature toggle enabled, without accepting agreement' => [
			'permissions' => [ 'ipinfo' ],
			'areBetaFeaturesLoaded' => true,
			'areTemporaryAccountsKnown' => false,
			'hasEnabledBetaFeature' => true,
			'hasAcceptedDataUseAgreement' => false,
			'isBlockedSitewide' => false,
			'expected' => false,
		];

		yield 'user with correct permissions on wiki with temporary accounts, without accepting agreement' => [
			'permissions' => [ 'ipinfo' ],
			'areBetaFeaturesLoaded' => true,
			'areTemporaryAccountsKnown' => true,
			'hasEnabledBetaFeature' => false,
			'hasAcceptedDataUseAgreement' => false,
			'isBlockedSitewide' => false,
			'expected' => false,
		];

		yield 'user with correct permissions on wiki without BetaFeatures, without accepting agreement' => [
			'permissions' => [ 'ipinfo' ],
			'areBetaFeaturesLoaded' => false,
			'areTemporaryAccountsKnown' => false,
			'hasEnabledBetaFeature' => false,
			'hasAcceptedDataUseAgreement' => false,
			'isBlockedSitewide' => false,
			'expected' => false,
		];

		yield 'user with correct permissions with BetaFeature toggle enabled, with accepted agreement' => [
			'permissions' => [ 'ipinfo' ],
			'areBetaFeaturesLoaded' => true,
			'areTemporaryAccountsKnown' => false,
			'hasEnabledBetaFeature' => true,
			'hasAcceptedDataUseAgreement' => true,
			'isBlockedSitewide' => false,
			'expected' => true,
		];

		yield 'user with correct permissions on wiki with temporary accounts, with accepted agreement' => [
			'permissions' => [ 'ipinfo' ],
			'areBetaFeaturesLoaded' => true,
			'areTemporaryAccountsKnown' => true,
			'hasEnabledBetaFeature' => false,
			'hasAcceptedDataUseAgreement' => true,
			'isBlockedSitewide' => false,
			'expected' => true,
		];

		yield 'user with correct permissions on wiki without BetaFeatures, with accepted agreement' => [
			'permissions' => [ 'ipinfo' ],
			'areBetaFeaturesLoaded' => false,
			'areTemporaryAccountsKnown' => false,
			'hasEnabledBetaFeature' => false,
			'hasAcceptedDataUseAgreement' => true,
			'isBlockedSitewide' => false,
			'expected' => true,
		];

		yield 'sitewide blocked user with other requirements met' => [
			'permissions' => [ 'ipinfo' ],
			'areBetaFeaturesLoaded' => true,
			'areTemporaryAccountsKnown' => true,
			'hasEnabledBetaFeature' => false,
			'hasAcceptedDataUseAgreement' => true,
			'isBlockedSitewide' => true,
			'expected' => true,
		];
	}

	/**
	 * @dataProvider provideRequiresBetaFeatureToggle
	 */
	public function testRequiresBetaFeatureToggle(
		bool $areBetaFeaturesLoaded,
		bool $areTemporaryAccountsKnown,
		bool $expected
	): void {
		$this->extensionRegistry->method( 'isLoaded' )
			->with( 'BetaFeatures' )
			->willReturn( $areBetaFeaturesLoaded );

		$this->tempUserConfig->method( 'isKnown' )
			->willReturn( $areTemporaryAccountsKnown );

		$requiresBetaFeatureToggle = $this->ipInfoPermissionManager->requiresBetaFeatureToggle();

		$this->assertSame( $expected, $requiresBetaFeatureToggle );
	}

	public static function provideRequiresBetaFeatureToggle(): iterable {
		yield 'BetaFeatures loaded, temporary accounts not known' => [
			'areBetaFeaturesLoaded' => true,
			'areTemporaryAccountsKnown' => false,
			'expected' => true,
		];

		yield 'BetaFeatures loaded, temporary accounts known' => [
			'areBetaFeaturesLoaded' => true,
			'areTemporaryAccountsKnown' => true,
			'expected' => false,
		];

		yield 'BetaFeatures not loaded, temporary accounts not known' => [
			'areBetaFeaturesLoaded' => false,
			'areTemporaryAccountsKnown' => false,
			'expected' => false,
		];

		yield 'BetaFeatures not loaded, temporary accounts known' => [
			'areBetaFeaturesLoaded' => false,
			'areTemporaryAccountsKnown' => true,
			'expected' => false,
		];
	}
}
