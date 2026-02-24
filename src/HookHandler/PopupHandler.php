<?php

namespace MediaWiki\IPInfo\HookHandler;

use MediaWiki\IPInfo\IPInfoPermissionManager;
use MediaWiki\Output\Hook\BeforePageDisplayHook;

class PopupHandler implements BeforePageDisplayHook {
	public function __construct(
		private readonly IPInfoPermissionManager $ipInfoPermissionManager,
	) {
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
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
