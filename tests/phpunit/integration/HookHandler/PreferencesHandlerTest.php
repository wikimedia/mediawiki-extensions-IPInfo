<?php

namespace MediaWiki\IPInfo\Test\Unit\HookHandler;

use MediaWiki\IPInfo\HookHandler\PreferencesHandler;
use MediaWiki\IPInfo\IPInfoPermissionManager;
use MediaWiki\IPInfo\Logging\Logger;
use MediaWiki\IPInfo\Logging\LoggerFactory;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\HookHandler\PreferencesHandler
 */
class PreferencesHandlerTest extends MediaWikiIntegrationTestCase {

	private function getPreferencesHandler( array $options = [] ): PreferencesHandler {
		return new PreferencesHandler( ...array_values( array_merge(
			[
				'ipInfoPermissionManager' => $this->createMock( IPInfoPermissionManager::class ),
				'userGroupManager' => $this->createMock( UserGroupManager::class ),
				'loggerFactory' => $this->createMock( LoggerFactory::class ),
			],
			$options
		) ) );
	}

	/**
	 * @dataProvider provideOnSaveUserOptionsNoAccessChange
	 */
	public function testOnSaveUserOptionsNoAccessChange( $originalOptions, $modifiedOptions ) {
		$user = $this->createMock( UserIdentity::class );

		$logger = $this->createMock( Logger::class );
		$logger->expects( $this->never() )
			->method( 'logAccessDisabled' );
		$logger->expects( $this->never() )
			->method( 'logAccessEnabled' );

		$loggerFactory = $this->createMock( LoggerFactory::class );
		$loggerFactory->method( 'getLogger' )
			->willReturn( $logger );

		$handler = $this->getPreferencesHandler( [
			'loggerFactory' => $loggerFactory,
		] );

		$handler->onSaveUserOptions( $user, $modifiedOptions, $originalOptions );
	}

	public static function provideOnSaveUserOptionsNoAccessChange() {
		return [
			'Enabled to begin with, then not set' => [
				[
					'ipinfo-use-agreement' => true,
				],
				[],
			],
			'Enabled to begin with, then both option set to truthy' => [
				[
					'ipinfo-use-agreement' => true,
				],
				[
					'ipinfo-use-agreement' => '1',
				],
			],
			'Disabled to begin with, then not set' => [
				[
					'ipinfo-use-agreement' => false,
				],
				[],
			],
			'Disabled to begin with, then set to falsey' => [
				[
					'ipinfo-use-agreement' => 0,
				],
				[
					'ipinfo-use-agreement' => false,
				],
			],
			'No options set to begin with, then no options set' => [
				[],
				[],
			],
		];
	}

	/**
	 * @dataProvider provideOnSaveUserOptionsRestoreDefaultPreferences
	 */
	public function testOnSaveUserOptionsRestoreDefaultPreferences( $originalOptions, $modifiedOptions ) {
		$user = $this->createMock( UserIdentity::class );
		$logger = $this->createMock( Logger::class );

		$loggerFactory = $this->createMock( LoggerFactory::class );
		$loggerFactory->method( 'getLogger' )
			->willReturn( $logger );
		$handler = $this->getPreferencesHandler( [
			'loggerFactory' => $loggerFactory,
		] );

		$handler->onSaveUserOptions( $user, $modifiedOptions, $originalOptions );

		$this->assertFalse( $modifiedOptions[ 'ipinfo-use-agreement' ] );
	}

	public static function provideOnSaveUserOptionsRestoreDefaultPreferences() {
		return [
			'Disable beta feature' => [
				[
					'ipinfo-beta-feature-enable' => true
				],
				[
					'ipinfo-beta-feature-enable' => false
				],
			],
			'Enable beta feature' => [
				[
					'ipinfo-beta-feature-enable' => false
				],
				[
					'ipinfo-beta-feature-enable' => true
				],
			],
			'Enable auto enroll' => [
				[
					'ipinfo-beta-feature-enable' => false,
					'betafeatures-auto-enroll' => false
				],
				[
					'betafeatures-auto-enroll' => true
				],
			],
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

		$logger = $this->createMock( Logger::class );
		$loggerFactory = $this->createMock( LoggerFactory::class );
		$loggerFactory->method( 'getLogger' )
			->willReturn( $logger );

		$ipInfoPermissionManager = $this->createMock( IPInfoPermissionManager::class );
		$ipInfoPermissionManager->method( 'hasEnabledIPInfo' )
			->with( $user )
			->willReturn( $hasEnabledIPInfo );

		$handler = $this->getPreferencesHandler( [
			'loggerFactory' => $loggerFactory,
			'ipInfoPermissionManager' => $ipInfoPermissionManager,
		] );

		$preferences = [];
		$handler->onGetPreferences( $user, $preferences );
		$this->assertSame( $expectedPreferenceNames, array_keys( $preferences ) );
	}

	public function provideGetPreferences(): iterable {
		yield 'user with IPInfo access enabled' => [ true, [ 'ipinfo-use-agreement' ] ];
		yield 'user with IPInfo access disabled' => [ false, [] ];
	}
}
