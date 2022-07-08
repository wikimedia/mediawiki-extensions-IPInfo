<?php

namespace MediaWiki\IPInfo\Test\Integration\HookHandler;

use Hooks;
use MediaWiki\Permissions\PermissionManager;
use MediaWikiIntegrationTestCase;
use User;

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
		Hooks::run( 'GetBetaFeaturePreferences', [ $user, &$preferences ] );
		$this->assertArrayHasKey( 'ipinfo-beta-feature-enable', $preferences );
	}
}
