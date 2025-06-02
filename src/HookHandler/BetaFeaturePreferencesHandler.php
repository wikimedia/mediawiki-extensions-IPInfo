<?php
namespace MediaWiki\IPInfo\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\IPInfo\IPInfoPermissionManager;
use MediaWiki\User\User;

class BetaFeaturePreferencesHandler {

	private Config $config;

	private IPInfoPermissionManager $ipInfoPermissionManager;

	public function __construct(
		Config $config,
		IPInfoPermissionManager $ipInfoPermissionManager
	) {
		$this->config = $config;
		$this->ipInfoPermissionManager = $ipInfoPermissionManager;
	}

	/**
	 * @param User $user
	 * @param array[] &$betaPrefs
	 */
	public function onGetBetaFeaturePreferences( $user, &$betaPrefs ) {
		$extensionAssetsPath = $this->config->get( 'ExtensionAssetsPath' );

		if (
			$this->ipInfoPermissionManager->requiresBetaFeatureToggle() &&
			$user->isAllowed( 'ipinfo' )
		) {
			$url = "https://www.mediawiki.org/wiki/";
			$infoLink = $url . "Trust_and_Safety_Product/IP_Info";
			$discussionLink = $url . "Talk:Trust_and_Safety_Product/IP_Info";

			$betaPrefs['ipinfo-beta-feature-enable'] = [
				'label-message' => 'ipinfo-beta-feature-title',
				'desc-message' => 'ipinfo-beta-feature-description',
				'screenshot' => [
					'ltr' => "$extensionAssetsPath/IPInfo/src/images/ipinfo-icon-ltr.svg",
					'rtl' => "$extensionAssetsPath/IPInfo/src/images/ipinfo-icon-rtl.svg",
				],
				'info-link' => $infoLink,
				'discussion-link' => $discussionLink,
				'requirements' => [
					'javascript' => true,
				],
			];
		}
	}
}
