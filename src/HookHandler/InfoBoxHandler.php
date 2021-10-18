<?php

namespace MediaWiki\IPInfo\HookHandler;

use CollapsibleFieldsetLayout;
use Mediawiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\User\UserOptionsLookup;
use OOUI\PanelLayout;
use OutputPage;
use Wikimedia\IPUtils;

class InfoBoxHandler implements SpecialPageBeforeExecuteHook {
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
	public function onSpecialPageBeforeExecute( $special, $subPage ): void {
		$out = $special->getOutput();

		if ( !( $out->getTitle() && $out->getTitle()->isSpecial( 'Contributions' ) ) ) {
			return;
		}

		$user = $out->getUser();
		if (
			!$this->permissionManager->userHasRight( $user, 'ipinfo' ) ||
			!$this->userOptionsLookup->getOption( $user, 'ipinfo-enable' )
		) {
			return;
		}

		// Check if either the target parameter or the subpage is an IP address
		$target = $out->getRequest()->getVal( 'target' );
		if ( IPUtils::isValid( $target ) ) {
			$target = IPUtils::prettifyIP( $target );
		} elseif ( IPUtils::isValid( $subPage ) ) {
			$target = IPUtils::prettifyIP( $subPage );
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

		$isExpanded = (bool)$this->userOptionsLookup->getOption( $user, 'ipinfo-infobox-expanded' );

		$panelLayout = new PanelLayout( [
			'classes' => [ 'ext-ipinfo-panel-layout' ],
			'framed' => true,
			'expanded' => false,
			'padded' => true,
			'content' => ( new CollapsibleFieldsetLayout(
				[
					'label' => $out->getContext()->msg( 'ipinfo-infobox-title' ),
					'collapsed' => !$isExpanded,
					'classes' => [ 'ext-ipinfo-collapsible-layout' ],
					'infusable' => true,
				]
			) ),
		] );
		OutputPage::setupOOUI();
		$out->addHTML( $panelLayout );
	}
}
