<?php
namespace MediaWiki\IPInfo\Test\Unit\HookHandler;

use ArrayUtils;
use MediaWiki\IPInfo\HookHandler\PopupHandler;
use MediaWiki\IPInfo\IPInfoPermissionManager;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Skin\Skin;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\IPInfo\HookHandler\PopupHandler
 */
class PopupHandlerTest extends MediaWikiUnitTestCase {
	/**
	 * @dataProvider provideDisplayOptions
	 *
	 * @param string|null $actionName The action name, or `null` for no action.
	 * @param string|null $specialPageName The special page name, or `null` to simulate a non-special page.
	 * @param bool $canViewIPInfo
	 */
	public function testShouldAddModules(
		?string $actionName,
		?string $specialPageName,
		bool $canViewIPInfo
	): void {
		$request = new FauxRequest( [ 'action' => $actionName ] );

		$title = $this->createMock( Title::class );
		$title->method( 'isSpecial' )
			->willReturnMap( [
				[ $specialPageName, true ]
			] );

		$user = new UserIdentityValue( 1, 'TestUser' );
		$performer = new SimpleAuthority( $user, [ 'ipinfo' ] );

		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getRequest' )
			->willReturn( $request );
		$outputPage->method( 'getTitle' )
			->willReturn( $title );
		$outputPage->method( 'getAuthority' )
			->willReturn( $performer );

		// The IPInfo popup should only be loaded on the history page and specific special pages,
		// and only if the user is authorized to view IP information.
		if (
			( $actionName === 'history' || in_array( $specialPageName, [ 'Log', 'Recentchanges', 'Watchlist' ] ) ) &&
			$canViewIPInfo
		) {
			$outputPage->expects( $this->once() )
				->method( 'addModules' )
				->with( 'ext.ipInfo' );

			$outputPage->expects( $this->once() )
				->method( 'addModuleStyles' )
				->with( 'ext.ipInfo.styles' );
		} else {
			$outputPage->expects( $this->never() )
				->method( $this->logicalOr( 'addModules', 'addModuleStyles' ) );
		}

		$ipInfoPermissionManager = $this->createMock( IPInfoPermissionManager::class );
		$ipInfoPermissionManager->method( 'canViewIPInfo' )
			->with( $performer )
			->willReturn( $canViewIPInfo );

		$popupHandler = new PopupHandler( $ipInfoPermissionManager, null );

		$popupHandler->onBeforePageDisplay( $outputPage, $this->createMock( Skin::class ) );
	}

	public static function provideDisplayOptions(): iterable {
		$testCases = ArrayUtils::cartesianProduct(
			// action name
			[ null, 'history', 'info' ],
			// special page name
			[ null, 'Log', 'Block' ],
			// whether the user can view IP information
			[ true, false ],
		);

		foreach ( $testCases as $params ) {
			[
				$actionName,
				$specialPageName,
				$canViewIPInfo
			] = $params;

			// Special pages can't have actions.
			if ( $actionName !== null && $specialPageName !== null ) {
				continue;
			}

			$description = sprintf(
				'%s%s%s',
				$actionName ? "action=$actionName, " : '',
				$specialPageName ? "Special:$specialPageName, " : '',
				$canViewIPInfo ? 'with access' : 'without access',
			);

			yield $description => $params;
		}
	}
}
