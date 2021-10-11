<?php

namespace MediaWiki\IPInfo\HookHandler;

use MediaWiki\Hook\BeforePageDisplayHook;
use Mediawiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\UserOptionsLookup;

class PreferencesHandler implements
	BeforePageDisplayHook,
	GetPreferencesHook
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
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$user = $out->getUser();
		if (
			$out->getTitle() &&
			$out->getTitle()->isSpecial( 'Preferences' ) &&
			$this->permissionManager->userHasRight( $user, 'ipinfo' ) ) {
			$out->addModules( 'ext.ipInfo.preferences' );
		}
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
		$preferences['ipinfo-use-agreement'] = [
			'type' => 'toggle',
			'label-message' => 'ipinfo-preference-use-agreement',
			'section' => 'personal/ipinfo',
			'validation-callback' => [
				__CLASS__,
				'checkAllIPInfoAgreements'
			]
		];
		$preferences[ 'ipinfo-infobox-expanded' ] = [
			'type' => 'api',
			'default' => 0,
		];
	}

	/**
	 * Check pre-requisite ipinfo-preference-enable is checked
	 * if ipinfo-preference-use-agreement is checked
	 *
	 * @param string|null $value Value of ipinfo-preference-use-agreement
	 * @param array $allData All form data
	 * @return bool|string|null true on success, string on error
	 */
	public static function checkAllIPInfoAgreements( ?string $value, array $allData ) {
		if ( $value === null ) {
			// Return true because form is still setting default values
			// so there's nothing to check against yet
			return true;
		}

		// If ipinfo-preference-use-agreement isn't checked, no need to validate
		if ( !$value ) {
			return true;
		}

		// If it is, check that ipinfo-enable is also checked
		$ipInfoEnable = $allData['ipinfo-enable'];
		if ( !$ipInfoEnable ) {
			return wfMessage( 'ipinfo-preference-agreement-error' );
		}

		// Both are checked
		return true;
	}
}
