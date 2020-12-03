<?php

namespace MediaWiki\IPInfo\HookHandler;

use Mediawiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\User\UserOptionsLookup;
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
	public function onSpecialPageBeforeExecute( $special, $subPage ) : void {
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
		if ( IPUtils::isIPAddress( $target ) ) {
			$target = IPUtils::prettifyIP( $target );
		} elseif ( IPUtils::isIPAddress( $subPage ) ) {
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
	}
}
