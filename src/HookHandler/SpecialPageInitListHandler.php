<?php
namespace MediaWiki\IPInfo\HookHandler;

use MediaWiki\IPInfo\Special\SpecialIPInfo;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\User\TempUser\TempUserConfig;

/**
 * Conditionally register Special:IPInfo if temporary users are known on the local wiki.
 */
class SpecialPageInitListHandler implements SpecialPage_initListHook {

	private TempUserConfig $tempUserConfig;

	public function __construct( TempUserConfig $tempUserConfig ) {
		$this->tempUserConfig = $tempUserConfig;
	}

	/**
	 * Conditionally register Special:IPInfo if temporary users are known on the local wiki.
	 *
	 * @param array &$list Associative array of special page descriptors keyed by special page name, passed
	 * by reference.
	 * @return void
	 */
	public function onSpecialPage_initList( &$list ): void {
		if ( $this->tempUserConfig->isKnown() ) {
			$list['IPInfo'] = [
				'class' => SpecialIPInfo::class,
				'services' => [
					'UserOptionsManager',
					'UserNameUtils',
					'LocalServerObjectCache',
					'IPInfoTempUserIPLookup',
					'UserIdentityLookup',
					'IPInfoInfoManager',
					'PermissionManager'
				]
			];
		}
	}
}
