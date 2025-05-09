<?php

/**
 * Copy of CentralAuth's CentralAuthServiceWiringTest.php that tests
 * the ServiceWiring.php file for the IPInfo extension.
 */

namespace MediaWiki\IPInfo\Test\Integration;

use MediaWikiIntegrationTestCase;

/**
 * @coversNothing
 */
class ServiceWiringTest extends MediaWikiIntegrationTestCase {
	/** @dataProvider provideService */
	public function testService( string $name ) {
		$this->getServiceContainer()->get( $name );
		$this->addToAssertionCount( 1 );
	}

	public static function provideService() {
		$wiring = require __DIR__ . '/../../../src/ServiceWiring.php';
		foreach ( $wiring as $name => $_ ) {
			yield $name => [ $name ];
		}
	}
}
