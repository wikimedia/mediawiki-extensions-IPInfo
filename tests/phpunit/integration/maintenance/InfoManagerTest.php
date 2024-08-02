<?php

namespace MediaWiki\IPInfo\Test\Integration\Maintenance;

use MediaWiki\IPInfo\Maintenance\InfoManager;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\IPInfo\Maintenance\InfoManager
 */
class InfoManagerTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return InfoManager::class;
	}

	public function testExecute() {
		// Mock the IPInfoInfoManager service to return some fake data
		$ipInfoManager = $this->createMock( \MediaWiki\IPInfo\InfoManager::class );
		$ipInfoManager->method( 'retrieveFor' )
			->with( '1.2.3.4' )
			->willReturn( [ 'subject' => '1.2.3.4', 'data' => [ 'test' ] ] );
		$this->setService( 'IPInfoInfoManager', $ipInfoManager );
		// Run the maintenance script
		$this->maintenance->setOption( 'ip', '1.2.3.4' );
		$this->maintenance->execute();
		$this->expectOutputString( '{"subject":"1.2.3.4","data":["test"]}' );
	}
}
