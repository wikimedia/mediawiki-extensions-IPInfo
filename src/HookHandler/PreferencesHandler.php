<?php

namespace MediaWiki\IPInfo\HookHandler;

use MediaWiki\IPInfo\IPInfoPermissionManager;
use MediaWiki\IPInfo\Logging\LoggerFactory;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;

class PreferencesHandler extends AbstractPreferencesHandler implements GetPreferencesHook {
	private IPInfoPermissionManager $ipInfoPermissionManager;
	private LoggerFactory $loggerFactory;

	public function __construct(
		IPInfoPermissionManager $ipInfoPermissionManager,
		UserGroupManager $userGroupManager,
		ExtensionRegistry $extensionRegistry,
		LoggerFactory $loggerFactory
	) {
		parent::__construct( $extensionRegistry, $userGroupManager );
		$this->ipInfoPermissionManager = $ipInfoPermissionManager;
		$this->loggerFactory = $loggerFactory;
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ): void {
		if ( !$this->ipInfoPermissionManager->hasEnabledIPInfo( $user ) ) {
			return;
		}

		$preferences[self::IPINFO_USE_AGREEMENT] = [
			'type' => 'toggle',
			'label-message' => 'ipinfo-preference-use-agreement',
			'section' => 'personal/ipinfo',
			'noglobal' => true,
		];
	}

	/**
	 * @param UserIdentity $user
	 * @param array &$modifiedOptions
	 * @param array $originalOptions
	 */
	public function onSaveUserOptions( UserIdentity $user, array &$modifiedOptions, array $originalOptions ) {
		$betaFeatureIsEnabled = $this->isTruthy( $originalOptions, 'ipinfo-beta-feature-enable' );
		$betaFeatureIsDisabled = !$betaFeatureIsEnabled;

		$betaFeatureWillEnable = $this->isTruthy( $modifiedOptions, 'ipinfo-beta-feature-enable' );
		$betaFeatureWillDisable = $this->isFalsey( $modifiedOptions, 'ipinfo-beta-feature-enable' );

		// If enabling auto-enroll, treat as enabling IPInfo because:
		// * IPInfo will become enabled
		// * 'ipinfo-beta-feature-enable' won't be updated before this hook runs
		// When disabling auto-enroll, do not treat as disabling IPnfo because:
		// * IPInfo will not necessarily become disabled
		// * 'ipinfo-beta-feature-enable' will be updated if IPInfo becomes disabled
		$autoEnrollIsEnabled = $this->isTruthy( $originalOptions, 'betafeatures-auto-enroll' );
		$autoEnrollIsDisabled = !$autoEnrollIsEnabled;
		$autoEnrollWillEnable = $this->isTruthy( $modifiedOptions, 'betafeatures-auto-enroll' );

		if (
			( $betaFeatureIsEnabled && $betaFeatureWillDisable ) ||
			( $betaFeatureIsDisabled && $betaFeatureWillEnable ) ||
			( $betaFeatureIsDisabled && $autoEnrollIsDisabled && $autoEnrollWillEnable )
		) {
			// Restore default IPInfo preferences
			$modifiedOptions[self::IPINFO_USE_AGREEMENT] = false;
		}

		// Is IPInfo already enabled?
		$ipInfoAgreementIsEnabled = $this->isTruthy( $originalOptions, self::IPINFO_USE_AGREEMENT );
		$ipInfoIsEnabled = $betaFeatureIsEnabled && $ipInfoAgreementIsEnabled;
		$ipInfoIsDisabled = !$ipInfoIsEnabled;

		$ipInfoAgreementWillEnable = $this->isTruthy( $modifiedOptions, self::IPINFO_USE_AGREEMENT );
		$ipInfoAgreementWillDisable = $this->isFalsey( $modifiedOptions, self::IPINFO_USE_AGREEMENT );
		$ipInfoWillEnable = $betaFeatureIsEnabled && !$betaFeatureWillDisable && $ipInfoAgreementWillEnable;
		$ipInfoWillDisable = $betaFeatureWillDisable || $ipInfoAgreementWillDisable;

		if ( ( !$ipInfoAgreementIsEnabled && $ipInfoAgreementWillEnable ) ||
			( $ipInfoAgreementIsEnabled && $ipInfoAgreementWillDisable ) ) {
			$this->logEvent(
				(bool)$modifiedOptions[self::IPINFO_USE_AGREEMENT] ? 'accept_disclaimer' : 'uncheck_iagree',
				'page',
				'special_preferences',
				$user
			);
		}

		if ( ( $ipInfoIsEnabled && $ipInfoWillDisable ) ||
			( $ipInfoIsDisabled && $ipInfoWillEnable )
		) {
			$logger = $this->loggerFactory->getLogger();
			if ( $ipInfoWillEnable ) {
				$logger->logAccessEnabled( $user );
			} else {
				$logger->logAccessDisabled( $user );
			}

			$this->logEvent(
				$ipInfoWillEnable ? 'enable_ipinfo' : 'disable_ipinfo',
				'page',
				'special_preferences',
				$user
			);
		}
	}
}
