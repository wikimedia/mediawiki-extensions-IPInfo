<?php

namespace MediaWiki\IPInfo\HookHandler;

use Mediawiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;

class Preferences implements GetPreferencesHook {
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
	public function onGetPreferences( $user, &$preferences ): void {
		if ( !$this->permissionManager->userHasRight( $user, 'ipinfo' ) ) {
			return;
		}

		$preferences['ipinfo-enable'] = [
			'type' => 'toggle',
			'label-message' => 'ipinfo-preference-enable',
			'section' => 'personal/ipinfo',
		];
	}
}
