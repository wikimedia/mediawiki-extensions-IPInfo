<?php
namespace MediaWiki\IPInfo\Test\Unit\HookHandler;

use ArrayUtils;
use MediaWiki\IPInfo\HookHandler\PopupHandler;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Skin\Skin;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\StaticUserOptionsLookup;
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
	 * @param bool $areBetaFeaturesEnabled Whether the BetaFeatures extension is enabled.
	 * @param bool $hasIpInfoRight Whether the user has the "ipinfo" right.
	 * @param bool $hasAcceptedIpInfoAgreement Whether the user has accepted the IPInfo data use agreement.
	 * @param bool $hasEnabledIpInfoBetaFeature Whether the user has enabled the IPInfo beta feature.
	 */
	public function testShouldAddModules(
		?string $actionName,
		?string $specialPageName,
		bool $areBetaFeaturesEnabled,
		bool $hasIpInfoRight,
		bool $hasAcceptedIpInfoAgreement,
		bool $hasEnabledIpInfoBetaFeature
	): void {
		$request = new FauxRequest( [ 'action' => $actionName ] );

		$title = $this->createMock( Title::class );
		$title->method( 'isSpecial' )
			->willReturnMap( [
				[ $specialPageName, true ]
			] );

		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->willReturnMap( [
				[ 'BetaFeatures', '*', $areBetaFeaturesEnabled ],
			] );

		$user = new UserIdentityValue( 1, 'TestUser' );
		$performer = new SimpleAuthority( $user, $hasIpInfoRight ? [ 'ipinfo' ] : [] );

		$userOptionsLookup = new StaticUserOptionsLookup( [
			$user->getName() => [
				'ipinfo-use-agreement' => $hasAcceptedIpInfoAgreement ? 1 : 0,
				'ipinfo-beta-feature-enable' => $hasEnabledIpInfoBetaFeature ? 1 : 0,
			]
		] );

		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getRequest' )
			->willReturn( $request );
		$outputPage->method( 'getTitle' )
			->willReturn( $title );
		$outputPage->method( 'getAuthority' )
			->willReturn( $performer );

		// The IPInfo popup should only be loaded on the history page and specific special pages,
		// and only for users with the requisite permission that have accepted the data use agreement.
		// With BetaFeatures enabled, they also need to have enabled the IPInfo BetaFeature.
		if (
			$hasIpInfoRight &&
			$hasAcceptedIpInfoAgreement &&
			( $actionName === 'history' || in_array( $specialPageName, [ 'Log', 'Recentchanges', 'Watchlist' ] ) ) &&
			( !$areBetaFeaturesEnabled || $hasEnabledIpInfoBetaFeature )
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

		$popupHandler = new PopupHandler(
			$userOptionsLookup,
			$extensionRegistry,
			 null
		);

		$popupHandler->onBeforePageDisplay( $outputPage, $this->createMock( Skin::class ) );
	}

	public static function provideDisplayOptions(): iterable {
		$testCases = ArrayUtils::cartesianProduct(
			// action name
			[ null, 'history', 'info' ],
			// special page name
			[ null, 'Log', 'Block' ],
			// whether the BetaFeatures extension is enabled
			[ true, false ],
			// whether the performer has the "ipinfo" right
			[ true, false ],
			// whether the user has accepted the IPInfo data use agreement
			[ true, false ],
			// whether the user has enabled the IPInfo beta feature
			[ true, false ]
		);

		foreach ( $testCases as $params ) {
			[
				$actionName,
				$specialPageName,
				$areBetaFeaturesEnabled,
				$hasIpInfoRight,
				$hasAcceptedIpInfoAgreement,
				$hasEnabledIpInfoBetaFeature
			] = $params;

			// Special pages can't have actions.
			if ( $actionName !== null && $specialPageName !== null ) {
				continue;
			}

			// Only test the IPInfo BetaFeature option when we simulate the extension being loaded.
			if ( !$areBetaFeaturesEnabled && $hasEnabledIpInfoBetaFeature ) {
				continue;
			}

			$description = sprintf(
				'%s%sBetaFeatures %s, %s IPInfo permission, ' .
				'IPInfo data use agreement %s, IPInfo beta feature %s',
				$actionName ? "action=$actionName, " : '',
				$specialPageName ? "Special:$specialPageName, " : '',
				$areBetaFeaturesEnabled ? 'enabled' : 'disabled',
				$hasIpInfoRight ? 'with' : 'without',
				$hasAcceptedIpInfoAgreement ? 'accepted' : 'not accepted',
				$hasEnabledIpInfoBetaFeature ? 'enabled' : 'disabled'
			);

			yield $description => $params;
		}
	}
}
