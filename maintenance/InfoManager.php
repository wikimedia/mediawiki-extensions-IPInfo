<?php

namespace MediaWiki\IPInfo\Maintenance;

use MediaWiki\Json\FormatJson;
use MediaWiki\Maintenance\Maintenance;
use Wikimedia\IPUtils;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class InfoManager extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Retrieve data for an IP address.' );
		$this->addOption( 'ip', 'The IP address to use in the lookup.', true );
		$this->requireExtension( 'IPInfo' );
	}

	public function execute() {
		$ip = $this->getOption( 'ip' );
		if ( !IPUtils::isValid( $ip ) ) {
			$this->fatalError( "$ip is not a valid IP address." );
		}
		/** @var \MediaWiki\IPInfo\InfoManager $ipInfoManager */
		$ipInfoManager = $this->getServiceContainer()->getService( 'IPInfoInfoManager' );
		$result = $ipInfoManager->retrieveFor( $ip, $ip );
		$this->output( FormatJson::encode( $result ) );
	}
}

// @codeCoverageIgnoreStart
$maintClass = InfoManager::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
