<?php

namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use GeoIp2\Database\Reader;
use MediaWiki\IPInfo\InfoRetriever\ReaderFactory;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\ReaderFactory
 */
class ReaderFactoryTest extends MediaWikiUnitTestCase {
	private function getFactory() {
		return new ReaderFactory();
	}

	public function testCreateFactory() {
		$factory = $this->getFactory();
		$this->assertNotNull( $factory, "Factory not created." );
		$this->assertInstanceOf( ReaderFactory::class, $factory );
	}

	public function testGetReturnsNullWhenPathDoesNotExist() {
		$factory = $this->getFactory();
		$actual = $factory->get( '/broken/path/', 'filename' );
		$this->assertNull( $actual );
	}

	public function testGetReaderThrowsException() {
		$this->expectException( \InvalidArgumentException::class );
		$factory = $this->getFactory();
		TestingAccessWrapper::newFromObject( $factory )->getReader( '/broken/path/filename' );
	}

	public function testGetReader() {
		$factory = $this->getMockBuilder( ReaderFactory::class )
			->onlyMethods( [ 'getReader' ] )
			->getMock();
		$factory->expects( $this->once() )
			->method( 'getReader' )
			->willReturn( $this->createMock( Reader::class ) );
		$factory->get( '/path/', 'filename' );
		$factory->get( '/path/', 'filename' );
	}
}
