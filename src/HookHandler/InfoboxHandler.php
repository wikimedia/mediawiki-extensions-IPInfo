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
	 * This function is used to add an info box on Special:Contributions and Special:DeletedContributions
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
		if ( !( IPUtils::isValid( $username ) || $this->tempUserConfig->isTempName( $username ) ) ) {
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
		if ( $sp->getName() !== 'Contributions' ) {
			return;
		}

		$this->addInfoBox( $user->getName(), $sp );
	}

	/** @inheritDoc */
	public function onSpecialPageBeforeExecute( $sp, $subPage ) {
		if ( $sp->getName() !== 'DeletedContributions' ) {
			return;
		}

		if ( $subPage === null ) {
			$subPage = $sp->getRequest()->getText( 'target' );
		}

		$this->addInfoBox( $subPage, $sp );
	}
}
