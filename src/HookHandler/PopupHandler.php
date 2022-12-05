<?php

namespace MediaWiki\IPInfo\HookHandler;

use ExtensionRegistry;
use MediaWiki\Hook\BeforePageDisplayHook;
use Mediawiki\Permissions\PermissionManager;
use MediaWiki\User\UserOptionsLookup;

class PopupHandler implements BeforePageDisplayHook {
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
	public function onBeforePageDisplay( $out, $skin ): void {
		if (
			$out->getRequest()->getVal( 'action' ) !== 'history' &&
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
		$isBetaFeaturesLoaded = ExtensionRegistry::getInstance()->isLoaded( 'BetaFeatures' );

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
