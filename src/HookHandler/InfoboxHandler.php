<?php

namespace MediaWiki\IPInfo\HookHandler;

use MediaWiki\Hook\SpecialContributionsBeforeMainOutputHook;
use MediaWiki\HTMLForm\CollapsibleFieldsetLayout;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use OOUI\PanelLayout;
use Wikimedia\IPUtils;

class InfoboxHandler implements
	SpecialContributionsBeforeMainOutputHook,
	SpecialPageBeforeExecuteHook
{

	private UserOptionsLookup $userOptionsLookup;

	private TempUserConfig $tempUserConfig;

	public function __construct(
		UserOptionsLookup $userOptionsLookup,
		TempUserConfig $tempUserConfig
	) {
		$this->userOptionsLookup = $userOptionsLookup;
		$this->tempUserConfig = $tempUserConfig;
	}

	/**
	 * This function is used to add an info box on special pages.
	 *
	 * Please refer to dispatcher.js in modules/ext.ipInfo for a list of special
	 * pages the infobox is added to.
	 *
	 * @param string $username Username or IP Address
	 * @param SpecialPage $sp
	 */
	private function addInfoBox( $username, $sp ): void {
		// T309363: hide the panel on mobile until T268177 is resolved
		$services = MediaWikiServices::getInstance();
		$extensionRegistry = ExtensionRegistry::getInstance();
		if (
			$extensionRegistry->isLoaded( 'MobileFrontend' ) &&
			$services->getService( 'MobileFrontend.Context' )->shouldDisplayMobileView()
		) {
			return;
		}

		$accessingUser = $sp->getAuthority();
		$isBetaFeaturesLoaded = $extensionRegistry->isLoaded( 'BetaFeatures' );
		if (
			!$accessingUser->isAllowed( 'ipinfo' ) ||
			( $isBetaFeaturesLoaded &&
				!$this->userOptionsLookup->getOption( $accessingUser->getUser(), 'ipinfo-beta-feature-enable' )
			)
		) {
			return;
		}

		// Check if the target is an anonymous or temporary user.
		if ( !IPUtils::isValid( $username ) && !$this->tempUserConfig->isTempName( $username ) ) {
			return;
		}

		$out = $sp->getOutput();
		$out->addModules( 'ext.ipInfo' );
		$out->addModuleStyles( 'ext.ipInfo.styles' );
		$panelLayout = new PanelLayout( [
			'classes' => [ 'ext-ipinfo-panel-layout' ],
			'framed' => true,
			'expanded' => false,
			'padded' => true,
			'content' => ( new CollapsibleFieldsetLayout(
				[
					'label' => $sp->msg( 'ipinfo-infobox-title' ),
					'collapsed' => true,
					'classes' => [ 'ext-ipinfo-collapsible-layout' ],
					'infusable' => true,
				]
			) ),
		] );
		OutputPage::setupOOUI();
		$out->addHTML( $panelLayout );
	}

	/** @inheritDoc */
	public function onSpecialContributionsBeforeMainOutput( $id, $user, $sp ) {
		switch ( $sp->getName() ) {
			case 'Contributions':
				// T379049 For Special:Contributions, unless the request targets
				// an actual IP (i.e. an anonymous user), log entries for a
				// given user may originate from different IPs; therefore, the
				// infobox is only shown on the first page.
				if ( $this->isFirstPage( $sp ) || $user->isAnon() ) {
					$this->addInfoBox( $user->getName(), $sp );
				}

				break;

			case 'IPContributions':
				// T379049 As all log entries in Special:IPContributions refer
				// to the same IP, it always shows the infobox. However, we
				// first ensure the target provided in the URL is really an IP,
				// so we don't show an empty infobox in a page that just shows
				// an error.
				if ( $user->isAnon() ) {
					$this->addInfoBox( $user->getName(), $sp );
				}

				break;

			default:
				// Do nothing
		}
	}

	/** @inheritDoc */
	public function onSpecialPageBeforeExecute( $sp, $subPage ) {
		if ( $sp->getName() !== 'DeletedContributions' ) {
			return;
		}

		if ( $subPage === null ) {
			$subPage = $sp->getRequest()->getText( 'target' );
		}

		// T379049 Unless the request targets an actual IP, log entries for a
		// given user in Special:DeletedContributions may originate from
		// different IPs, so the infobox is only shown on the first page.
		if ( $this->isFirstPage( $sp ) || IPUtils::isIPAddress( $subPage ) ) {
			$this->addInfoBox( $subPage, $sp );
		}
	}

	private function isFirstPage( SpecialPage $sp ): bool {
		return ( !( $sp->getRequest()->getIntOrNull( 'offset' ) > 0 ) ) &&
			( $sp->getRequest()->getText( 'dir' ) !== 'prev' );
	}
}
