<?php
namespace MediaWiki\IPInfo;

use MediaWiki\IPInfo\HookHandler\PreferencesHandler;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;

/**
 * Service for managing IPInfo-related access checks.
 */
class IPInfoPermissionManager {
	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly TempUserConfig $tempUserConfig,
	) {
	}

	/**
	 * Check whether the given user should be allowed to see IPInfo's data use preference and infobox.
	 *
	 * Use {@link IPInfoPermissionManager::canViewIPInfo()} to check whether the user is authorized
	 * to access IP information.
	 *
	 * @param Authority $accessingUser
	 * @return bool
	 */
	public function hasEnabledIPInfo( Authority $accessingUser ): bool {
		if ( !$accessingUser->isAllowed( 'ipinfo' ) ) {
			return false;
		}

		if ( $this->requiresBetaFeatureToggle() ) {
			return $this->userOptionsLookup->getBoolOption( $accessingUser->getUser(), 'ipinfo-beta-feature-enable' );
		}

		return true;
	}

	/**
	 * Check whether IPInfo access requires opting into a BetaFeatures toggle.
	 * @return bool `true` if BetaFeatures is loaded and temporary accounts are not known on this wiki,
	 * `false` otherwise.
	 */
	public function requiresBetaFeatureToggle(): bool {
		// Only gate IPInfo behind a BetaFeatures toggle on wikis without temporary accounts (T356660).
		return $this->extensionRegistry->isLoaded( 'BetaFeatures' ) && !$this->tempUserConfig->isKnown();
	}

	/**
	 * Check whether the given user should be allowed to see IPInfo and has accepted the data use agreement.
	 * Note that this does not check blocks targeting the given user to allow using it to conditionally render
	 * UI elements that display a block notice.
	 *
	 * @param Authority $accessingUser
	 * @return bool
	 */
	public function canViewIPInfo( Authority $accessingUser ): bool {
		return $this->hasEnabledIPInfo( $accessingUser ) &&
			$this->userOptionsLookup->getBoolOption(
				$accessingUser->getUser(), PreferencesHandler::IPINFO_USE_AGREEMENT
			);
	}
}
