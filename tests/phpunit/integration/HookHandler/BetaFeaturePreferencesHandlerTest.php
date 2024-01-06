<?php

namespace MediaWiki\IPInfo\Test\Integration\HookHandler;

use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\HookHandler\BetaFeaturePreferencesHandler
 */
class BetaFeaturePreferencesHandlerTest extends MediaWikiIntegrationTestCase {
	public function testOnGetBetaFeaturePreferences() {
		$this->overrideMwServices(
			null,
			[
				'PermissionManager' => function () {
					$permissionManager = $this->createMock( PermissionManager::class );
					$permissionManager->method( 'userHasRight' )
						->willReturn( true );
					return $permissionManager;
				}
			]
		);

		$user = $this->createMock( User::class );
		$preferences = [];
		$this->getServiceContainer()->getHookContainer()->run( 'GetBetaFeaturePreferences', [ $user, &$preferences ] );
		$this->assertArrayHasKey( 'ipinfo-beta-feature-enable', $preferences );
	}
}
