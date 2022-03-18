<?php

namespace MediaWiki\IPInfo\Jobs;

use Job;
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
		$timestamp = $this->params['timestamp'];
		$level = $this->params['access_level'];

		if ( !$performer ) {
			$this->setLastError( 'Invalid performer' );
			return false;
		}

		$factory = MediaWikiServices::getInstance()->get( 'IPInfoLoggerFactory' );
		$logger = $factory->getLogger();

		switch ( $this->params['dataContext'] ) {
			case 'infobox':
				$logger->logViewInfobox( $performer, $ip, $timestamp, $level );
				break;
			case 'popup':
				$logger->logViewPopup( $performer, $ip, $timestamp, $level );
				break;
			default:
				$this->setLastError( 'Invalid dataContext: ' . $this->params['dataContext'] );
				return false;
		}
		return true;
	}
}
