<?php

namespace MediaWiki\IPInfo\HookHandler;

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
					 $out->getTitle()->isSpecial( 'Recentchanges' ) ) )
		) {
			return;
		}

		$user = $out->getUser();
		if (
			!$this->permissionManager->userHasRight( $user, 'ipinfo' ) ||
			!$this->userOptionsLookup->getOption( $user, 'ipinfo-enable' )
		) {
			return;
		}

		$out->addModules( 'ext.ipInfo' );
		$out->addModuleStyles( 'ext.ipInfo.styles' );
	}
}
