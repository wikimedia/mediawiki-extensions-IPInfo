<?php

namespace MediaWiki\IPInfo\HookHandler;

use MediaWiki\IPInfo\IPInfoPermissionManager;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MobileContext;

class PopupHandler implements BeforePageDisplayHook {
	public function __construct(
		private readonly IPInfoPermissionManager $ipInfoPermissionManager,
		private readonly ?MobileContext $mobileContext,
	) {
	}

	public static function factory(): self {
		$services = MediaWikiServices::getInstance();

		$mobileContext = null;

		if ( $services->getExtensionRegistry()->isLoaded( 'MobileFrontend' ) ) {
			$mobileContext = $services->getService( 'MobileFrontend.Context' );
		}

		return new self(
			$services->getService( 'IPInfoPermissionManager' ),
			$mobileContext
		);
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		// T339861: Don't load on mobile until T268177 is resolved
		if ( $this->mobileContext && $this->mobileContext->shouldDisplayMobileView() ) {
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

		if ( !$this->ipInfoPermissionManager->canViewIPInfo( $out->getAuthority() ) ) {
			return;
		}

		$out->addModules( 'ext.ipInfo' );
		$out->addModuleStyles( 'ext.ipInfo.styles' );
	}
}
