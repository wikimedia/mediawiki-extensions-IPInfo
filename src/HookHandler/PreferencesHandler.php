<?php

namespace MediaWiki\IPInfo\HookHandler;

use ExtensionRegistry;
use MediaWiki\Extension\EventLogging\EventLogging;
use MediaWiki\Extension\EventLogging\Libs\UserBucketProvider\UserBucketProvider;
use MediaWiki\IPInfo\Logging\LoggerFactory;
use Mediawiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;

class PreferencesHandler implements GetPreferencesHook {
	/** @var PermissionManager */
	private $permissionManager;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var UserGroupManager */
	private $userGroupManager;

	/** @var LoggerFactory */
	private $loggerFactory;

	/**
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserGroupManager $userGroupManager
	 * @param LoggerFactory $loggerFactory
	 */
	public function __construct(
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserGroupManager $userGroupManager,
		LoggerFactory $loggerFactory
	) {
		$this->permissionManager = $permissionManager;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userGroupManager = $userGroupManager;
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
			!$this->userOptionsLookup->getOption( $user, 'ipinfo-beta-feature-enable' )
		) {
			return;
		}

		$preferences['ipinfo-use-agreement'] = [
			'type' => 'toggle',
			'label-message' => 'ipinfo-preference-use-agreement',
			'section' => 'personal/ipinfo',
			'noglobal' => true,
		];
	}

	/**
	 * Utility function to make option checking less verbose.
	 *
	 * @param array $options
	 * @param string $option
	 * @return bool The option is set and truthy
	 */
	private function isTruthy( $options, $option ): bool {
		return !empty( $options[$option] );
	}

	/**
	 * Utility function to make option checking less verbose.
	 * We avoid empty() here because we need the option to be set.
	 *
	 * @param array $options
	 * @param string $option
	 * @return bool The option is set and falsey
	 */
	private function isFalsey( $options, $option ): bool {
		return isset( $options[$option] ) && !$options[$option];
	}

	/**
	 * Send events to the external event server
	 *
	 * @param string $action
	 * @param string $context
	 * @param string $source
	 * @param UserIdentity $user
	 */
	private function logEvent( $action, $context, $source, $user ) {
		$eventLoggingLoaded = ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' );
		if ( !$eventLoggingLoaded ) {
			return;
		}
		EventLogging::submit( 'mediawiki.ipinfo_interaction', [
			'$schema' => '/analytics/mediawiki/ipinfo_interaction/1.1.0',
			'event_action' => $action,
			'event_context' => $context,
			'event_source' => $source,
			'user_edit_bucket' => UserBucketProvider::getUserEditCountBucket( $user ),
			'user_groups' => implode( '|', $this->userGroupManager->getUserGroups( $user ) )
		] );
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
			$modifiedOptions[ 'ipinfo-use-agreement' ] = false;
		}

		// Is IPInfo already enabled?
		$ipInfoAgreementIsEnabled = $this->isTruthy( $originalOptions, 'ipinfo-use-agreement' );
		$ipInfoIsEnabled = $betaFeatureIsEnabled && $ipInfoAgreementIsEnabled;
		$ipInfoIsDisabled = !$ipInfoIsEnabled;

		$ipInfoAgreementWillEnable = $this->isTruthy( $modifiedOptions, 'ipinfo-use-agreement' );
		$ipInfoAgreementWillDisable = $this->isFalsey( $modifiedOptions, 'ipinfo-use-agreement' );
		$ipInfoWillEnable = $betaFeatureIsEnabled && !$betaFeatureWillDisable && $ipInfoAgreementWillEnable;
		$ipInfoWillDisable = $betaFeatureWillDisable || $ipInfoAgreementWillDisable;

		if ( ( !$ipInfoAgreementIsEnabled && $ipInfoAgreementWillEnable ) ||
			( $ipInfoAgreementIsEnabled && $ipInfoAgreementWillDisable ) ) {
			$this->logEvent(
				(bool)$modifiedOptions['ipinfo-use-agreement'] ? 'accept_disclaimer' : 'uncheck_iagree',
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
