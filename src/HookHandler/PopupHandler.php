<?php

namespace MediaWiki\IPInfo\HookHandler;

use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Options\UserOptionsLookup;
use MobileContext;

class PopupHandler implements BeforePageDisplayHook {
	private UserOptionsLookup $userOptionsLookup;
	private ExtensionRegistry $extensionRegistry;
	private ?MobileContext $mobileContext;

	public function __construct(
		UserOptionsLookup $userOptionsLookup,
		ExtensionRegistry $extensionRegistry,
		?MobileContext $mobileContext
	) {
		$this->userOptionsLookup = $userOptionsLookup;
		$this->extensionRegistry = $extensionRegistry;
		$this->mobileContext = $mobileContext;
	}

	public static function factory(): self {
		$services = MediaWikiServices::getInstance();

		$mobileContext = null;

		if ( $services->getExtensionRegistry()->isLoaded( 'MobileFrontend' ) ) {
			$mobileContext = $services->getService( 'MobileFrontend.Context' );
		}

		return new self(
			$services->getUserOptionsLookup(),
			$services->getExtensionRegistry(),
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

		$user = $out->getAuthority()->getUser();
		$isBetaFeaturesLoaded = $this->extensionRegistry->isLoaded( 'BetaFeatures' );

		if (
			!$out->getAuthority()->isAllowed( 'ipinfo' ) ||
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
