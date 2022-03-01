<?php

namespace MediaWiki\IPInfo\HookHandler;

use ExtensionRegistry;
use MediaWiki\IPInfo\Logging\LoggerFactory;
use Mediawiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;

class PreferencesHandler implements GetPreferencesHook {
	/** @var PermissionManager */
	private $permissionManager;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var LoggerFactory */
	private $loggerFactory;

	/**
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param LoggerFactory $loggerFactory
	 */
	public function __construct(
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		LoggerFactory $loggerFactory
	) {
		$this->permissionManager = $permissionManager;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->loggerFactory = $loggerFactory;
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
		$betaFeatureIsEnabled = isset( $originalOptions['ipinfo-beta-feature-enable'] ) &&
			$originalOptions['ipinfo-beta-feature-enable'];
		$betaFeatureIsDisabled = !$betaFeatureIsEnabled;

		$betaFeatureWillEnable = isset( $modifiedOptions['ipinfo-beta-feature-enable'] ) &&
			$modifiedOptions['ipinfo-beta-feature-enable'];
		$betaFeatureWillDisable = isset( $modifiedOptions['ipinfo-beta-feature-enable'] ) &&
			!$modifiedOptions['ipinfo-beta-feature-enable'];

		// If enabling auto-enroll, treat as enabling IPInfo because:
		// * IPInfo will become enabled
		// * 'ipinfo-beta-feature-enable' won't be updated before this hook runs
		// When disabling auto-enroll, do not treat as disabling IPnfo because:
		// * IPInfo will not necessarily become disabled
		// * 'ipinfo-beta-feature-enable' will be updated if IPInfo becomes disabled
		$autoEnrollIsDisabled = !(
			isset( $originalOptions['betafeatures-auto-enroll'] ) &&
			$originalOptions['betafeatures-auto-enroll']
		);
		$autoEnrollWillEnable = isset( $modifiedOptions['betafeatures-auto-enroll'] ) &&
			$modifiedOptions['betafeatures-auto-enroll'];

		if (
			$betaFeatureIsEnabled && $betaFeatureWillDisable ||
			$betaFeatureIsDisabled && $betaFeatureWillEnable ||
			$betaFeatureIsDisabled && $autoEnrollIsDisabled && $autoEnrollWillEnable
		) {
			// Restore default IPInfo preferences
			$modifiedOptions[ 'ipinfo-enable' ] = true;
			$modifiedOptions[ 'ipinfo-use-agreement' ] = false;
		}

		$isEnabled = isset( $originalOptions[ 'ipinfo-enable' ] ) &&
			isset( $originalOptions[ 'ipinfo-use-agreement' ] ) &&
			$originalOptions[ 'ipinfo-enable' ] &&
			$originalOptions[ 'ipinfo-use-agreement' ];
		$willEnable = isset( $modifiedOptions[ 'ipinfo-enable' ] ) &&
			isset( $modifiedOptions[ 'ipinfo-use-agreement' ] ) &&
			$modifiedOptions[ 'ipinfo-enable' ] &&
			$modifiedOptions[ 'ipinfo-use-agreement' ];
		if ( $isEnabled !== $willEnable ) {
			$logger = $this->loggerFactory->getLogger();
			if ( $willEnable ) {
				$logger->logAccessEnabled( $user );
			} else {
				$logger->logAccessDisabled( $user );
			}
		}
	}
}
