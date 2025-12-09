<?php

namespace MediaWiki\IPInfo\HookHandler;

use MediaWiki\IPInfo\IPInfoPermissionManager;
use MediaWiki\IPInfo\Logging\LoggerFactory;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Options\Hook\LocalUserOptionsStoreSaveHook;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsManager;

class PreferencesHandler extends AbstractPreferencesHandler implements
	GetPreferencesHook,
	LocalUserOptionsStoreSaveHook
{
	public function __construct(
		private readonly IPInfoPermissionManager $ipInfoPermissionManager,
		UserGroupManager $userGroupManager,
		UserOptionsManager $userOptionsManager,
		ExtensionRegistry $extensionRegistry,
		private readonly LoggerFactory $loggerFactory,
	) {
		parent::__construct( $extensionRegistry, $userGroupManager, $userOptionsManager );
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
		];
	}

	/** @inheritDoc */
	public function onLocalUserOptionsStoreSave( UserIdentity $user, array $oldOptions, array $newOptions ): void {
		$this->applyDefaultsToPreferenceArrays( $user, $oldOptions, $newOptions );

		if ( $this->ipInfoPermissionManager->requiresBetaFeatureToggle() ) {
			$betaFeatureIsEnabled = $this->isTruthy( $oldOptions, 'ipinfo-beta-feature-enable' );
			$betaFeatureWillDisable = $this->isFalsey( $newOptions, 'ipinfo-beta-feature-enable' );
			$betaFeatureWillEnable = $this->isTruthy( $newOptions, 'ipinfo-beta-feature-enable' );
		} else {
			// IPInfo is not a BetaFeature if temporary accounts are known on this wiki (T356660).
			$betaFeatureIsEnabled = true;
			$betaFeatureWillDisable = false;
			$betaFeatureWillEnable = true;
		}

		// Is IPInfo already enabled?
		$ipInfoAgreementIsEnabled = $this->isTruthy( $oldOptions, self::IPINFO_USE_AGREEMENT );
		$ipInfoIsEnabled = $betaFeatureIsEnabled && $ipInfoAgreementIsEnabled;
		$ipInfoIsDisabled = !$ipInfoIsEnabled;

		$ipInfoAgreementWillEnable = $this->isTruthy( $newOptions, self::IPINFO_USE_AGREEMENT );
		$ipInfoAgreementWillDisable = $this->isFalsey( $newOptions, self::IPINFO_USE_AGREEMENT );
		$ipInfoWillEnable = $betaFeatureWillEnable && $ipInfoAgreementWillEnable;
		$ipInfoWillDisable = $betaFeatureWillDisable || $ipInfoAgreementWillDisable;

		if ( ( !$ipInfoAgreementIsEnabled && $ipInfoAgreementWillEnable ) ||
			( $ipInfoAgreementIsEnabled && $ipInfoAgreementWillDisable ) ) {
			$this->logEvent(
				(bool)$newOptions[self::IPINFO_USE_AGREEMENT] ? 'accept_disclaimer' : 'uncheck_iagree',
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
