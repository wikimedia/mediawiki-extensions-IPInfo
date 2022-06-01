<?php

namespace MediaWiki\IPInfo\HookHandler;

use CollapsibleFieldsetLayout;
use ExtensionRegistry;
use MediaWiki\Hook\SpecialContributionsBeforeMainOutputHook;
use MediaWiki\MediaWikiServices;
use Mediawiki\Permissions\PermissionManager;
use MediaWiki\User\UserOptionsLookup;
use OOUI\PanelLayout;
use OutputPage;
use Wikimedia\IPUtils;

class InfoboxHandler implements SpecialContributionsBeforeMainOutputHook {
	/** @var PermissionManager */
	private $permissionManager;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/**
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup
	) {
		$this->permissionManager = $permissionManager;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function onSpecialContributionsBeforeMainOutput( $id, $user, $sp ): void {
		$out = $sp->getOutput();
		if ( !( $out->getTitle() && $out->getTitle()->isSpecial( 'Contributions' ) ) ) {
			return;
		}

		// T309363: hide the panel on mobile until T268177 is resolved
		$services = MediaWikiServices::getInstance();
		if ( ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) &&
			$services->getService( 'MobileFrontend.Context' )
				->shouldDisplayMobileView() ) {
			return;
		}

		$accessingUser = $out->getUser();
		$isBetaFeaturesLoaded = ExtensionRegistry::getInstance()->isLoaded( 'BetaFeatures' );

		if (
			!$this->permissionManager->userHasRight( $accessingUser, 'ipinfo' ) ||
			!$this->userOptionsLookup->getOption( $accessingUser, 'ipinfo-enable' ) ||
			$isBetaFeaturesLoaded &&
			!$this->userOptionsLookup->getOption( $accessingUser, 'ipinfo-beta-feature-enable' )
			) {
			return;
		}

		// Check if the target is an IP address
		$target = $user->getName();
		if ( IPUtils::isValid( $target ) ) {
			$target = IPUtils::prettifyIP( $target );
		} else {
			$target = null;
		}
		if ( !$target ) {
			return;
		}
		$out->addJsConfigVars( [
			'wgIPInfoTarget' => $target
		] );

		$out->addModules( 'ext.ipInfo' );
		$out->addModuleStyles( 'ext.ipInfo.styles' );

		$panelLayout = new PanelLayout( [
			'classes' => [ 'ext-ipinfo-panel-layout' ],
			'framed' => true,
			'expanded' => false,
			'padded' => true,
			'content' => ( new CollapsibleFieldsetLayout(
				[
					'label' => $out->getContext()->msg( 'ipinfo-infobox-title' ),
					'collapsed' => true,
					'classes' => [ 'ext-ipinfo-collapsible-layout' ],
					'infusable' => true,
				]
			) ),
		] );
		OutputPage::setupOOUI();
		$out->addHTML( $panelLayout );
	}
}
