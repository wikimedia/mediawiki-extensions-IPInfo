<?php

namespace MediaWiki\IPInfo\HookHandler;

use MediaWiki\Extension\EventLogging\EventLogging;
use MediaWiki\Extension\EventLogging\Libs\UserBucketProvider\UserBucketProvider;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsManager;

/**
 * Class that {@link PreferencesHandler} is based on to support de-duplicating common code
 * for preference hook handler classes.
 */
abstract class AbstractPreferencesHandler {

	/** @var string The preference used to store if the user has agreed to the use agreement. */
	public const IPINFO_USE_AGREEMENT = 'ipinfo-use-agreement';

	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly UserGroupManager $userGroupManager,
		private readonly UserOptionsManager $userOptionsManager,
	) {
	}

	/**
	 * Utility function to make option checking less verbose.
	 *
	 * @param array $options
	 * @param string $option
	 * @return bool The option is set and truthy
	 */
	protected function isTruthy( array $options, string $option ): bool {
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
	protected function isFalsey( array $options, string $option ): bool {
		return isset( $options[$option] ) && !$options[$option];
	}

	/**
	 * Applies the default value for the 'ipinfo-use-agreement' preference based on the value
	 * provided by {@link UserOptionsManager::getOption}.
	 *
	 * @param UserIdentity $userIdentity
	 * @param array &$oldPreferences
	 * @param array &$newPreferences
	 * @return void
	 */
	protected function applyDefaultsToPreferenceArrays(
		UserIdentity $userIdentity, array &$oldPreferences, array &$newPreferences
	) {
		$ipInfoUseAgreementDefault = $this->userOptionsManager->getDefaultOption(
			self::IPINFO_USE_AGREEMENT, $userIdentity
		);
		$oldPreferences[self::IPINFO_USE_AGREEMENT] ??= $ipInfoUseAgreementDefault;
		$newPreferences[self::IPINFO_USE_AGREEMENT] ??= $ipInfoUseAgreementDefault;
	}

	/**
	 * Send events to the external event server
	 *
	 * @param string $action
	 * @param string $context
	 * @param string $source
	 * @param UserIdentity $user
	 */
	protected function logEvent( string $action, string $context, string $source, UserIdentity $user ): void {
		if ( !$this->extensionRegistry->isLoaded( 'EventLogging' ) ) {
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
}
