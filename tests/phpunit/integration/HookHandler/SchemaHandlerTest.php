<?php

namespace MediaWiki\IPInfo\Test\Integration\HookHandler;

use DatabaseUpdater;
use MediaWikiIntegrationTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\HookHandler\SchemaHandler
 */
class SchemaHandlerTest extends MediaWikiIntegrationTestCase {

	/**
	 * Check that the ipinfo_ip_changes table is created
	 */
	public function testSchemaUpdate() {
		$services = $this->getServiceContainer();
		$lbFactory = $services->getDBLoadBalancerFactory();
		$dbw = $lbFactory->getMainLB()->getConnectionRef( DB_PRIMARY );
		$dbw->dropTable( "ipinfo_ip_changes", __METHOD__ );
		$dbu = DatabaseUpdater::newForDB( $dbw );
		$dbu->doUpdates( [ "extensions" ] );
		$this->expectOutputRegex( '/(.*)Creating ipinfo_ip_changes table(.*)/' );
		$res = $dbw->select( "ipinfo_ip_changes", "*" );
		$row = $res->fetchRow();

		// There are no rows but this would have thrown an error if the table didn't exist
		$this->assertFalse( $row );
	}

}
