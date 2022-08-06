<?php

namespace MediaWiki\IPInfo\Test\Unit\Logging;

use MediaWiki\IPInfo\Logging\Logger;
use MediaWiki\IPInfo\Logging\LoggerFactory;
use MediaWiki\User\ActorStore;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\IPInfo\Logging\LoggerFactory
 */
class LoggerFactoryTest extends MediaWikiUnitTestCase {
	private function getFactory(): LoggerFactory {
		return new LoggerFactory(
			$this->createMock( ActorStore::class ),
			$this->createMock( IDatabase::class )
		);
	}

	public function testCreateFactory(): void {
		$factory = $this->getFactory();
		$this->assertInstanceOf( LoggerFactory::class, $factory );
	}

	public function testGetLogger(): void {
		$delay = 60;
		$factory = $this->getFactory();
		$logger = TestingAccessWrapper::newFromObject(
			$factory->getLogger( $delay )
		);
		$this->assertInstanceOf( Logger::class, $logger->object );
		$this->assertSame( $delay, $logger->delay, 'delay' );
	}
}
