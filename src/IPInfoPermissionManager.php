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
	private ExtensionRegistry $extensionRegistry;

	private UserOptionsLookup $userOptionsLookup;

	private TempUserConfig $tempUserConfig;

	public function __construct(
		ExtensionRegistry $extensionRegistry,
		UserOptionsLookup $userOptionsLookup,
		TempUserConfig $tempUserConfig
	) {
		$this->extensionRegistry = $extensionRegistry;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->tempUserConfig = $tempUserConfig;
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

		if ( !$this->extensionRegistry->isLoaded( 'BetaFeatures' ) ) {
			return true;
		}

		// Only gate IPInfo behind a BetaFeatures toggle on wikis without temporary accounts (T356660).
		return (
			$this->tempUserConfig->isKnown() ||
			$this->userOptionsLookup->getBoolOption( $accessingUser->getUser(), 'ipinfo-beta-feature-enable' )
		);
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
