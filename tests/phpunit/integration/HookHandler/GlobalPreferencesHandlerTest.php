<?php

namespace MediaWiki\IPInfo\Test\Integration\HookHandler;

use MediaWiki\IPInfo\HookHandler\GlobalPreferencesHandler;
use MediaWiki\IPInfo\Logging\Logger;
use MediaWiki\IPInfo\Logging\LoggerFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\HookHandler\GlobalPreferencesHandler
 */
class GlobalPreferencesHandlerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
	}

	/** @dataProvider provideOnGlobalPreferencesSetGlobalPreferences */
	public function testOnGlobalPreferencesSetGlobalPreferences(
		$oldOptions, $newOptions, $shouldLogAccessEnabled = false, $shouldLogAccessDisabled = false
	) {
		$user = $this->createMock( UserIdentity::class );

		$logger = $this->createMock( Logger::class );
		$logger->expects( $shouldLogAccessDisabled ? $this->once() : $this->never() )
			->method( 'logGlobalAccessDisabled' );
		$logger->expects( $shouldLogAccessEnabled ? $this->once() : $this->never() )
			->method( 'logGlobalAccessEnabled' );

		$loggerFactory = $this->createMock( LoggerFactory::class );
		$loggerFactory->method( 'getLogger' )
			->willReturn( $logger );

		$mockUserOptionsManager = $this->createMock( UserOptionsManager::class );
		$mockUserOptionsManager->method( 'getDefaultOption' )
			->willReturn( '0' );

		$handler = new GlobalPreferencesHandler(
			$this->createMock( ExtensionRegistry::class ),
			$this->createMock( UserGroupManager::class ),
			$mockUserOptionsManager,
			$loggerFactory
		);

		$handler->onGlobalPreferencesSetGlobalPreferences( $user, $oldOptions, $newOptions );
	}

	public static function provideOnGlobalPreferencesSetGlobalPreferences() {
		return [
			'Enabled to begin with, then option set to truthy' => [
				[
					GlobalPreferencesHandler::IPINFO_USE_AGREEMENT => true
				],
				[
					GlobalPreferencesHandler::IPINFO_USE_AGREEMENT => '1',
				],
			],
			'Explicitly disabled to begin with, then not set' => [
				[
					GlobalPreferencesHandler::IPINFO_USE_AGREEMENT => false,
				],
				[],
			],
			'Explicitly disabled to begin with, then set to falsey' => [
				[
					GlobalPreferencesHandler::IPINFO_USE_AGREEMENT => 0,
				],
				[
					GlobalPreferencesHandler::IPINFO_USE_AGREEMENT => false,
				],
			],
			'No options set to begin with, then no options set' => [ [], [] ],
			'Explicitly disabled to begin with, then enabled' => [
				[ GlobalPreferencesHandler::IPINFO_USE_AGREEMENT => false ],
				[ GlobalPreferencesHandler::IPINFO_USE_AGREEMENT => true ],
				true,
			],
			'Disabled to begin with (using default value of disabled), then enabled' => [
				[],
				[ GlobalPreferencesHandler::IPINFO_USE_AGREEMENT => true ],
				true,
			],
			'Enabled to begin with, then explictly disabled' => [
				[ GlobalPreferencesHandler::IPINFO_USE_AGREEMENT => 1 ],
				[ GlobalPreferencesHandler::IPINFO_USE_AGREEMENT => 0 ],
				false,
				true,
			],
			'Enabled to begin with, then disabled (by resetting to default value)' => [
				[ GlobalPreferencesHandler::IPINFO_USE_AGREEMENT => 1 ],
				[],
				false,
				true,
			],
		];
	}
}
