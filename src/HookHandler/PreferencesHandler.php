<?php

namespace MediaWiki\IPInfo\HookHandler;

use ExtensionRegistry;
use Mediawiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;

class PreferencesHandler implements GetPreferencesHook {
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
	public function onGetPreferences( $user, &$preferences ): void {
		if ( !$this->permissionManager->userHasRight( $user, 'ipinfo' ) ) {
			return;
		}

		$isBetaFeaturesLoaded = ExtensionRegistry::getInstance()->isLoaded( 'BetaFeatures' );
		// If the betafeature isn't enabled, do not show preferences checkboxes
		if ( $isBetaFeaturesLoaded &&
			!$this->userOptionsLookup->getOption( $user, 'ipinfo-beta-feature-enable' ) ) {
			return;
		}

		$preferences['ipinfo-enable'] = [
			'type' => 'toggle',
			'label-message' => 'ipinfo-preference-enable',
			'section' => 'personal/ipinfo',
			'noglobal' => true,
		];
		$preferences['ipinfo-use-agreement'] = [
			'type' => 'toggle',
			'label-message' => 'ipinfo-preference-use-agreement',
			'section' => 'personal/ipinfo',
			'validation-callback' => [
				__CLASS__,
				'checkAllIPInfoAgreements'
			],
			'disable-if' => [ '!==', 'ipinfo-enable', '1' ],
			'noglobal' => true,
		];
		$preferences[ 'ipinfo-infobox-expanded' ] = [
			'type' => 'api',
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

	/**
	 * @param UserIdentity $user
	 * @param array &$modifiedOptions
	 * @param array $originalOptions
	 */
	public function onSaveUserOptions( UserIdentity $user, array &$modifiedOptions, array $originalOptions ) {
		// The user is enabling IP Info beta feature.
		// We enable IP info tool by default.
		if (
			isset( $originalOptions[ 'ipinfo-beta-feature-enable' ] ) &&
			isset( $modifiedOptions[ 'ipinfo-beta-feature-enable' ] ) &&
			$originalOptions[ 'ipinfo-beta-feature-enable' ] == false &&
			$modifiedOptions[ 'ipinfo-beta-feature-enable' ] == true
		) {
			$modifiedOptions[ 'ipinfo-enable' ] = true;
			$modifiedOptions[ 'ipinfo-use-agreement' ] = false;
		}
	}
}
