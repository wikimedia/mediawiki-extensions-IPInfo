<?php
namespace MediaWiki\IPInfo\HookHandler;

use Config;
use Mediawiki\Permissions\PermissionManager;
use User;

class BetaFeaturePreferencesHandler {

	/** @var Config */
	private $config;

	/** @var PermissionManager */
	private $permissionManager;

	/**
	 * @param Config $config
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		Config $config,
		PermissionManager $permissionManager
	) {
		$this->config = $config;
		$this->permissionManager = $permissionManager;
	}

	/**
	 * @param User $user
	 * @param array[] &$betaPrefs
	 */
	public function onGetBetaFeaturePreferences( $user, &$betaPrefs ) {
		$extensionAssetsPath = $this->config->get( 'ExtensionAssetsPath' );

		if (
			$this->permissionManager->userHasRight( $user, 'ipinfo' )
		) {
			$url = "https://meta.wikimedia.org/wiki/";
			$infoLink = $url . "IP_Editing:_Privacy_Enhancement_and_Abuse_Mitigation/IP_Info_feature";
			$discussionLink = $url . "Talk:IP_Editing:_Privacy_Enhancement_and_Abuse_Mitigation/IP_Info_feature";

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
