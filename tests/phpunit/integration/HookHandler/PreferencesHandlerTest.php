<?php

namespace MediaWiki\IPInfo\Test\Unit\HookHandler;

use MediaWiki\IPInfo\HookHandler\PreferencesHandler;
use MediaWiki\IPInfo\IPInfoPermissionManager;
use MediaWiki\IPInfo\Logging\Logger;
use MediaWiki\IPInfo\Logging\LoggerFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\HookHandler\PreferencesHandler
 * @covers \MediaWiki\IPInfo\HookHandler\AbstractPreferencesHandler
 */
class PreferencesHandlerTest extends MediaWikiIntegrationTestCase {

	private IPInfoPermissionManager $ipInfoPermissionManager;
	private UserGroupManager $userGroupManager;
	private ExtensionRegistry $extensionRegistry;
	private LoggerFactory $loggerFactory;

	private Logger $logger;

	private PreferencesHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		$this->ipInfoPermissionManager = $this->createMock( IPInfoPermissionManager::class );
		$this->userGroupManager = $this->createMock( UserGroupManager::class );
		$this->extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$this->loggerFactory = $this->createMock( LoggerFactory::class );

		$this->logger = $this->createMock( Logger::class );

		$this->loggerFactory->method( 'getLogger' )
			->willReturn( $this->logger );

		$this->handler = new PreferencesHandler(
			$this->ipInfoPermissionManager,
			$this->userGroupManager,
			$this->extensionRegistry,
			$this->loggerFactory
		);
	}

	/**
	 * @dataProvider provideOnSaveUserOptionsNoAccessChange
	 */
	public function testOnSaveUserOptionsNoAccessChange(
		array $originalOptions,
		array $modifiedOptions,
		bool $isBetaFeatureOptInRequired
	) {
		$user = $this->createMock( UserIdentity::class );

		$this->ipInfoPermissionManager->method( 'requiresBetaFeatureToggle' )
			->willReturn( $isBetaFeatureOptInRequired );

		$this->logger->expects( $this->never() )
			->method( 'logAccessDisabled' );
		$this->logger->expects( $this->never() )
			->method( 'logAccessEnabled' );

		$this->handler->onSaveUserOptions( $user, $modifiedOptions, $originalOptions );
	}

	public static function provideOnSaveUserOptionsNoAccessChange() {
		return [
			'Enabled to begin with, then not set' => [
				[
					PreferencesHandler::IPINFO_USE_AGREEMENT => true,
				],
				[],
				false,
			],
			'Enabled to begin with, then both option set to truthy' => [
				[
					PreferencesHandler::IPINFO_USE_AGREEMENT => true,
				],
				[
					PreferencesHandler::IPINFO_USE_AGREEMENT => '1',
				],
				false,
			],
			'Disabled to begin with, then not set' => [
				[
					PreferencesHandler::IPINFO_USE_AGREEMENT => false,
				],
				[],
				false,
			],
			'Disabled to begin with, then set to falsey' => [
				[
					PreferencesHandler::IPINFO_USE_AGREEMENT => 0,
				],
				[
					PreferencesHandler::IPINFO_USE_AGREEMENT => false,
				],
				false,
			],
			'No options set to begin with, then no options set' => [
				[],
				[],
				false,
			],
			'Opting out of BetaFeature without accepting the agreement' => [
				[
					'ipinfo-beta-feature-enable' => true,
					PreferencesHandler::IPINFO_USE_AGREEMENT => false,
				],
				[
					'ipinfo-beta-feature-enable' => false,
					PreferencesHandler::IPINFO_USE_AGREEMENT => false,
				],
				true,
			],
			'Opting into BetaFeature without accepting the agreement' => [
				[
					'ipinfo-beta-feature-enable' => false,
					PreferencesHandler::IPINFO_USE_AGREEMENT => false,
				],
				[
					'ipinfo-beta-feature-enable' => true,
					PreferencesHandler::IPINFO_USE_AGREEMENT => false,
				],
				true,
			],
		];
	}

	/**
	 * @dataProvider provideOnSaveUserOptionsAccessChange
	 */
	public function testOnSaveUserOptionsAccessChange(
		array $originalOptions,
		array $modifiedOptions,
		bool $isBetaFeatureOptInRequired,
		bool $willBeEnabled
	) {
		$user = $this->createMock( UserIdentity::class );

		$this->ipInfoPermissionManager->method( 'requiresBetaFeatureToggle' )
			->willReturn( $isBetaFeatureOptInRequired );

		$this->logger->expects( $this->once() )
			->method( $willBeEnabled ? 'logAccessEnabled' : 'logAccessDisabled' );

		$this->handler->onSaveUserOptions( $user, $modifiedOptions, $originalOptions );
	}

	public static function provideOnSaveUserOptionsAccessChange(): iterable {
		yield 'accepting agreement without BetaFeatures' => [
			'originalOptions' => [ PreferencesHandler::IPINFO_USE_AGREEMENT => false ],
			'modifiedOptions' => [ PreferencesHandler::IPINFO_USE_AGREEMENT => true ],
			'isBetaFeatureOptInRequired' => false,
			'willBeEnabled' => true,
		];

		yield 'accepting agreement with BetaFeatures' => [
			'originalOptions' => [
				'ipinfo-beta-feature-enable' => true,
				PreferencesHandler::IPINFO_USE_AGREEMENT => false,
			],
			'modifiedOptions' => [
				'ipinfo-beta-feature-enable' => true,
				PreferencesHandler::IPINFO_USE_AGREEMENT => true,
			],
			'isBetaFeatureOptInRequired' => true,
			'willBeEnabled' => true,
		];

		yield 'accepting agreement without BetaFeatures, having previously opted out of the feature' => [
			'originalOptions' => [
				'ipinfo-beta-feature-enable' => false,
				PreferencesHandler::IPINFO_USE_AGREEMENT => false,
			],
			'modifiedOptions' => [
				'ipinfo-beta-feature-enable' => false,
				PreferencesHandler::IPINFO_USE_AGREEMENT => true,
			],
			'isBetaFeatureOptInRequired' => false,
			'willBeEnabled' => true,
		];

		yield 'opting out of agreement without BetaFeatures' => [
			'originalOptions' => [ PreferencesHandler::IPINFO_USE_AGREEMENT => true ],
			'modifiedOptions' => [ PreferencesHandler::IPINFO_USE_AGREEMENT => false ],
			'isBetaFeatureOptInRequired' => false,
			'willBeEnabled' => false,
		];

		yield 'opting out of agreement with BetaFeatures' => [
			'originalOptions' => [
				'ipinfo-beta-feature-enable' => true,
				PreferencesHandler::IPINFO_USE_AGREEMENT => true,
			],
			'modifiedOptions' => [
				'ipinfo-beta-feature-enable' => true,
				PreferencesHandler::IPINFO_USE_AGREEMENT => false,
			],
			'isBetaFeatureOptInRequired' => true,
			'willBeEnabled' => false,
		];

		yield 'disabling the BetaFeatures toggle after having accepted the agreement' => [
			'originalOptions' => [
				'ipinfo-beta-feature-enable' => true,
				PreferencesHandler::IPINFO_USE_AGREEMENT => true,
			],
			'modifiedOptions' => [
				'ipinfo-beta-feature-enable' => false,
				PreferencesHandler::IPINFO_USE_AGREEMENT => true,
			],
			'isBetaFeatureOptInRequired' => true,
			'willBeEnabled' => false,
		];

		yield 'disabling the BetaFeatures toggle and opting out of the agreement' => [
			'originalOptions' => [
				'ipinfo-beta-feature-enable' => true,
				PreferencesHandler::IPINFO_USE_AGREEMENT => true,
			],
			'modifiedOptions' => [
				'ipinfo-beta-feature-enable' => false,
				PreferencesHandler::IPINFO_USE_AGREEMENT => false,
			],
			'isBetaFeatureOptInRequired' => true,
			'willBeEnabled' => false,
		];

		yield 'reenabling the BetaFeatures toggle with a previously accepted agreement' => [
			'originalOptions' => [
				'ipinfo-beta-feature-enable' => false,
				PreferencesHandler::IPINFO_USE_AGREEMENT => true,
			],
			'modifiedOptions' => [
				'ipinfo-beta-feature-enable' => true,
				PreferencesHandler::IPINFO_USE_AGREEMENT => true,
			],
			'isBetaFeatureOptInRequired' => true,
			'willBeEnabled' => true,
		];
	}

	/**
	 * @dataProvider provideGetPreferences
	 */
	public function testOnGetPreferences(
		bool $hasEnabledIPInfo,
		array $expectedPreferenceNames
	) {
		$user = $this->createMock( User::class );

		$this->ipInfoPermissionManager->method( 'hasEnabledIPInfo' )
			->with( $user )
			->willReturn( $hasEnabledIPInfo );

		$preferences = [];
		$this->handler->onGetPreferences( $user, $preferences );
		$this->assertSame( $expectedPreferenceNames, array_keys( $preferences ) );
	}

	public static function provideGetPreferences(): iterable {
		yield 'user with IPInfo access enabled' => [ true, [ PreferencesHandler::IPINFO_USE_AGREEMENT ] ];
		yield 'user with IPInfo access disabled' => [ false, [] ];
	}
}
