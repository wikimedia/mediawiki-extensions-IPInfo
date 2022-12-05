<?php

namespace MediaWiki\IPInfo\HookHandler;

use CollapsibleFieldsetLayout;
use ExtensionRegistry;
use MediaWiki\Hook\SpecialContributionsBeforeMainOutputHook;
use MediaWiki\MediaWikiServices;
use Mediawiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\User\UserOptionsLookup;
use OOUI\PanelLayout;
use OutputPage;
use SpecialPage;
use Wikimedia\IPUtils;

class InfoboxHandler implements
	SpecialContributionsBeforeMainOutputHook,
	SpecialPageBeforeExecuteHook
{
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
	 * This fuction is used to add an info box on Special:Contributions and Special:DeletedContributions
	 *
	 * @param string $username Username or IP Address
	 * @param SpecialPage $sp
	 * @return void
	 */
	private function addInfoBox( $username, $sp ) {
		// T309363: hide the panel on mobile until T268177 is resolved
		$services = MediaWikiServices::getInstance();
		$extensionRegistry = ExtensionRegistry::getInstance();
		if (
			$extensionRegistry->isLoaded( 'MobileFrontend' ) &&
			$services->getService( 'MobileFrontend.Context' )->shouldDisplayMobileView()
		) {
			return;
		}

		$accessingUser = $sp->getUser();
		$isBetaFeaturesLoaded = $extensionRegistry->isLoaded( 'BetaFeatures' );
		if (
			!$this->permissionManager->userHasRight( $accessingUser, 'ipinfo' ) ||
			( $isBetaFeaturesLoaded &&
				!$this->userOptionsLookup->getOption( $accessingUser, 'ipinfo-beta-feature-enable' )
			)
		) {
			return;
		}
		// Check if the target is an IP address
		if ( IPUtils::isValid( $username ) ) {
			$username = IPUtils::prettifyIP( $username );
		} else {
			$username = null;
		}

		if ( !$username ) {
			return;
		}

		$out = $sp->getOutput();
		$out->addJsConfigVars( [
			'wgIPInfoTarget' => $username
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

	/**
	 * @inheritDoc
	 */
	public function onSpecialContributionsBeforeMainOutput( $id, $user, $sp ) {
		if ( $sp->getName() !== 'Contributions' ) {
			return;
		}

		$this->addInfoBox( $user->getName(), $sp );
	}

	/**
	 * @inheritDoc
	 */
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
