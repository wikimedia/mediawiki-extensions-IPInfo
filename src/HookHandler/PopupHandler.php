<?php

namespace MediaWiki\IPInfo\HookHandler;

use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Options\UserOptionsLookup;

class PopupHandler implements BeforePageDisplayHook {
	private PermissionManager $permissionManager;
	private UserOptionsLookup $userOptionsLookup;

	public function __construct(
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup
	) {
		$this->permissionManager = $permissionManager;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		// T339861: Don't load on mobile until T268177 is resolved
		$services = MediaWikiServices::getInstance();
		$extensionRegistry = ExtensionRegistry::getInstance();
		if (
			$extensionRegistry->isLoaded( 'MobileFrontend' ) &&
			$services->getService( 'MobileFrontend.Context' )->shouldDisplayMobileView()
		) {
			return;
		}

		if (
			$out->getRequest()->getRawVal( 'action' ) !== 'history' &&
			!( $out->getTitle() &&
				( $out->getTitle()->isSpecial( 'Log' ) ||
					 $out->getTitle()->isSpecial( 'Recentchanges' ) ||
					 $out->getTitle()->isSpecial( 'Watchlist' )
				)
			)
		) {
			return;
		}

		$user = $out->getUser();
		$isBetaFeaturesLoaded = $extensionRegistry->isLoaded( 'BetaFeatures' );

		if (
			!$this->permissionManager->userHasRight( $user, 'ipinfo' ) ||
			!$this->userOptionsLookup->getOption( $user, 'ipinfo-use-agreement' ) ||
			( $isBetaFeaturesLoaded &&
				!$this->userOptionsLookup->getOption( $user, 'ipinfo-beta-feature-enable' )
			)
		) {
			return;
		}

		$out->addModules( 'ext.ipInfo' );
		$out->addModuleStyles( 'ext.ipInfo.styles' );
	}
}
