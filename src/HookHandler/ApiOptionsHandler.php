<?php
namespace MediaWiki\IPInfo\HookHandler;

use MediaWiki\Api\Hook\ApiOptionsHook;
use MediaWiki\IPInfo\Logging\LoggerFactory;
use MediaWiki\User\UserOptionsLookup;

class ApiOptionsHandler implements ApiOptionsHook {
	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var LoggerFactory */
	private $loggerFactory;

	/**
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param LoggerFactory $loggerFactory
	 */
	public function __construct(
		UserOptionsLookup $userOptionsLookup,
		LoggerFactory $loggerFactory
	) {
		$this->userOptionsLookup = $userOptionsLookup;
		$this->loggerFactory = $loggerFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function onApiOptions( $apiModule, $user, $changes, $resetKinds ) {
		// Check that the user has already enabled IPInfo, has not yet agreed to terms of use
		// and is now accepting the terms of use (from the infobox), thereby enabling
		// their access to ip information from IPInfo
		if (
			$this->userOptionsLookup->getOption( $user, 'ipinfo-enable' ) &&
			!$this->userOptionsLookup->getOption( $user, 'ipinfo-use-agreement' ) &&
			isset( $changes[ 'ipinfo-use-agreement' ] ) &&
			$changes[ 'ipinfo-use-agreement' ]
		) {
			// Log the access change
			$logger = $this->loggerFactory->getLogger();
			$logger->logAccessEnabled( $user );
		}
	}
}
