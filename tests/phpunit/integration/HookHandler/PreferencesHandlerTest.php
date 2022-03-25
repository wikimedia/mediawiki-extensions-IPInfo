<?php

namespace MediaWiki\IPInfo\Test\Unit\HookHandler;

use MediaWiki\IPInfo\HookHandler\PreferencesHandler;
use MediaWiki\IPInfo\Logging\Logger;
use MediaWiki\IPInfo\Logging\LoggerFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiIntegrationTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\HookHandler\PreferencesHandler
 */
class PreferencesHandlerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @param array $options
	 * @return PreferencesnHandler
	 */
	private function getPreferencesHandler( array $options = [] ): PreferencesHandler {
		return new PreferencesHandler( ...array_values( array_merge(
			[
				'permissionManager' => $this->createMock( PermissionManager::class ),
				'userOptionsLookup' => $this->createMock( UserOptionsLookup::class ),
				'userGroupManager' => $this->createMock( UserGroupManager::class ),
				'loggerFactory' => $this->createMock( LoggerFactory::class ),
			],
			$options
		) ) );
	}

	/**
	 * @dataProvider provideOnSaveUserOptionsEnableAccess
	 */
	public function testOnSaveUserOptionsEnableAccess( $originalOptions, $modifiedOptions ) {
		$user = $this->createMock( UserIdentity::class );

		$logger = $this->createMock( Logger::class );
		$logger->expects( $this->once() )
			->method( 'logAccessEnabled' );
		$logger->expects( $this->never() )
			->method( 'logAccessDisabled' );

		$loggerFactory = $this->createMock( LoggerFactory::class );
		$loggerFactory->method( 'getLogger' )
			->willReturn( $logger );

		$handler = $this->getPreferencesHandler( [
			'loggerFactory' => $loggerFactory,
		] );

		$handler->onSaveUserOptions( $user, $modifiedOptions, $originalOptions );
	}

	public function provideOnSaveUserOptionsEnableAccess() {
		return [
			'Both options unset, then set' => [
				[],
				[
					'ipinfo-enable' => true,
					'ipinfo-use-agreement' => true,
				],
			],
			'Both options set falsey, then set truthy' => [
				[
					'ipinfo-enable' => 0,
					'ipinfo-use-agreement' => false,
				],
				[
					'ipinfo-enable' => '1',
					'ipinfo-use-agreement' => 1,
				],
			],
			'Basic enable switched on' => [
				[
					'ipinfo-use-agreement' => true,
				],
				[
					'ipinfo-enable' => true,
				],
			],
			'Agreement switched on' => [
				[
					'ipinfo-enable' => true,
				],
				[
					'ipinfo-use-agreement' => true,
				],
			],
		];
	}

	/**
	 * @dataProvider provideOnSaveUserOptionsDisableAccess
	 */
	public function testOnSaveUserOptionsDisableAccess( $originalOptions, $modifiedOptions ) {
		$user = $this->createMock( UserIdentity::class );

		$logger = $this->createMock( Logger::class );
		$logger->expects( $this->once() )
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

	public function provideOnSaveUserOptionsDisableAccess() {
		return [
			'Both options set truthy, then set falsey' => [
				[
					'ipinfo-enable' => '1',
					'ipinfo-use-agreement' => 1,
				],
				[
					'ipinfo-enable' => false,
					'ipinfo-use-agreement' => 0,
				],
			],
			'Basic enable switched off' => [
				[
					'ipinfo-enable' => true,
					'ipinfo-use-agreement' => true,
				],
				[
					'ipinfo-enable' => false,
				],
			],
			'Agreement switched off' => [
				[
					'ipinfo-enable' => true,
					'ipinfo-use-agreement' => true,
				],
				[
					'ipinfo-use-agreement' => false,
				],
			],
		];
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

	public function provideOnSaveUserOptionsNoAccessChange() {
		return [
			'Enabled to begin with, then no options set' => [
				[
					'ipinfo-enable' => true,
					'ipinfo-use-agreement' => true,
				],
				[],
			],
			'Enabled to begin with, then both options truthy' => [
				[
					'ipinfo-enable' => true,
					'ipinfo-use-agreement' => true,
				],
				[
					'ipinfo-enable' => 1,
					'ipinfo-use-agreement' => '1',
				],
			],
			'Disabled to begin with, then no options set' => [
				[
					'ipinfo-enable' => false,
					'ipinfo-use-agreement' => false,
				],
				[],
			],
			'Disabled to begin with, then both options falsey' => [
				[
					'ipinfo-enable' => false,
					'ipinfo-use-agreement' => 0,
				],
				[
					'ipinfo-enable' => 0,
					'ipinfo-use-agreement' => false,
				],
			],
			'No options set to begin with, then no options set' => [
				[],
				[],
			],
			'Basic enable switched on, agreement switched off' => [
				[
					'ipinfo-enable' => false,
					'ipinfo-use-agreement' => true,
				],
				[
					'ipinfo-enable' => true,
					'ipinfo-use-agreement' => false,
				],
			],
			'Basic enable switched off, agreement switched on' => [
				[
					'ipinfo-enable' => false,
					'ipinfo-use-agreement' => true,
				],
				[
					'ipinfo-enable' => true,
					'ipinfo-use-agreement' => false,
				],
			],
		];
	}

}
