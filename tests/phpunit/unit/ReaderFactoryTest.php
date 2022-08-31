<?php

namespace MediaWiki\IPInfo\Test\Unit;

use GeoIp2\Database\Reader;
use MediaWiki\IPInfo\InfoRetriever\ReaderFactory;
use MediaWiki\Languages\LanguageFallback;
use MediaWikiUnitTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\ReaderFactory
 */
class ReaderFactoryTest extends MediaWikiUnitTestCase {
	private function getFactory() {
		return new ReaderFactory( $this->createMock( LanguageFallback::class ) );
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

	public function testGetReader() {
		$factory = $this->getMockBuilder( ReaderFactory::class )
			->onlyMethods( [ 'getReader' ] )
			->setConstructorArgs( [ $this->createMock( LanguageFallback::class ) ] )
			->getMock();
		$factory->expects( $this->once() )
			->method( 'getReader' )
			->willReturn( $this->createMock( Reader::class ) );
		$factory->get( '/path/', 'filename' );
		$factory->get( '/path/', 'filename' );
	}
}
