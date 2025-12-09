<?php

namespace MediaWiki\IPInfo\HookHandler;

use GlobalPreferences\Hook\GlobalPreferencesSetGlobalPreferencesHook;
use MediaWiki\IPInfo\Logging\LoggerFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsManager;

class GlobalPreferencesHandler extends AbstractPreferencesHandler implements
	GlobalPreferencesSetGlobalPreferencesHook
{

	public function __construct(
		ExtensionRegistry $extensionRegistry,
		UserGroupManager $userGroupManager,
		UserOptionsManager $userOptionsManager,
		private readonly LoggerFactory $loggerFactory,
	) {
		parent::__construct( $extensionRegistry, $userGroupManager, $userOptionsManager );
	}

	/** @inheritDoc */
	public function onGlobalPreferencesSetGlobalPreferences(
		UserIdentity $user,
		array $oldPreferences,
		array $newPreferences
	): void {
		$this->applyDefaultsToPreferenceArrays( $user, $oldPreferences, $newPreferences );

		$wasUseAgreementEnabled = $this->isTruthy( $oldPreferences, self::IPINFO_USE_AGREEMENT );
		$wasUseAgreementDisabled = $this->isFalsey( $oldPreferences, self::IPINFO_USE_AGREEMENT );

		$willEnableUseAgreement = $this->isTruthy( $newPreferences, self::IPINFO_USE_AGREEMENT );
		$willDisableUseAgreement = $this->isFalsey( $newPreferences, self::IPINFO_USE_AGREEMENT );

		if (
			( $wasUseAgreementDisabled && $willEnableUseAgreement ) ||
			( $wasUseAgreementEnabled && $willDisableUseAgreement )
		) {
			$this->logEvent(
				$willEnableUseAgreement ? 'enable_ipinfo' : 'disable_ipinfo',
				'page',
				'special_preferences',
				$user
			);

			$logger = $this->loggerFactory->getLogger();
			if ( $willEnableUseAgreement ) {
				$logger->logGlobalAccessEnabled( $user );
			} else {
				$logger->logGlobalAccessDisabled( $user );
			}
		}
	}
}
