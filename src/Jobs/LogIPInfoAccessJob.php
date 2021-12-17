<?php

namespace MediaWiki\IPInfo\Jobs;

use Job;
use MediaWiki\IPInfo\Logging\Logger;
use MediaWiki\MediaWikiServices;

/**
 * Log when a user access information about an ip
 */
class LogIPInfoAccessJob extends Job {
	/**
	 * @inheritDoc
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'ipinfoLogIPInfoAccess', $params );
	}

	/**
	 * @return bool
	 */
	public function run() {
		$performer = MediaWikiServices::getInstance()->getUserIdentityLookup()
			->getUserIdentityByName( $this->params['performer'] );
		$ip = $this->params['ip'];

		if ( !$performer ) {
			$this->setLastError( 'Invalid performer' );
			return false;
		}

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_PRIMARY );
		$logger = new Logger( $dbw );

		switch ( $this->params['dataContext'] ) {
			case 'infobox':
				$logger->logViewInfobox( $performer, $ip );
				break;
			case 'popup':
				$logger->logViewPopup( $performer, $ip );
				break;
			default:
				$this->setLastError( 'Invalid dataContext: ' . $this->params['dataContext'] );
				return false;
		}
		return true;
	}
}
