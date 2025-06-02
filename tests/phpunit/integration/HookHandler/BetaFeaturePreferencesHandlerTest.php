<?php

namespace MediaWiki\IPInfo\Test\Integration\HookHandler;

use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\HookHandler\BetaFeaturePreferencesHandler
 */
class BetaFeaturePreferencesHandlerTest extends MediaWikiIntegrationTestCase {
	use TempUserTestTrait;

	/**
	 * @dataProvider provideGetBetaFeaturePreferences
	 */
	public function testOnGetBetaFeaturePreferences(
		bool $areTemporaryAccountsKnown,
		bool $hasIPInfoPermission,
		bool $shouldRegisterBetaFeature
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'BetaFeatures' );

		if ( $areTemporaryAccountsKnown ) {
			$this->enableAutoCreateTempUser( [
				'enabled' => false,
				'known' => true,
			] );
		} else {
			$this->disableAutoCreateTempUser();
		}

		$user = $this->createMock( User::class );
		$user->method( 'isAllowed' )
			->with( 'ipinfo' )
			->willReturn( $hasIPInfoPermission );

		$preferences = [];
		$this->getServiceContainer()->getHookContainer()->run( 'GetBetaFeaturePreferences', [ $user, &$preferences ] );

		if ( $shouldRegisterBetaFeature ) {
			$this->assertArrayHasKey( 'ipinfo-beta-feature-enable', $preferences );
		} else {
			$this->assertArrayNotHasKey( 'ipinfo-beta-feature-enable', $preferences );
		}
	}

	public static function provideGetBetaFeaturePreferences(): iterable {
		yield 'user with "ipinfo" permission, temporary accounts known' => [
			true,
			true,
			false,
		];

		yield 'user with "ipinfo" permission, temporary accounts not known' => [
			false,
			true,
			true,
		];

		yield 'user without "ipinfo" permission, temporary accounts known' => [
			true,
			false,
			false,
		];

		yield 'user without "ipinfo" permission, temporary accounts not known' => [
			false,
			false,
			false,
		];
	}
}
