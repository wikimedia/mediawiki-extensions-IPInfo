<?php

namespace MediaWiki\IPInfo\HookHandler;

use MediaWiki\Hook\BeforePageDisplayHook;
use Mediawiki\Permissions\PermissionManager;

class PopupHandler implements BeforePageDisplayHook {
	/** @var PermissionManager */
	private $permissionManager;

	/**
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		PermissionManager $permissionManager
	) {
		$this->permissionManager = $permissionManager;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ) : void {
		if ( $out->getRequest()->getVal( 'action' ) !== 'history' ) {
			return;
		}

		if ( !$this->permissionManager->userHasRight( $out->getUser(), 'ipinfo' ) ) {
			return;
		}

		$out->addModules( 'ext.ipInfo' );
	}
}
