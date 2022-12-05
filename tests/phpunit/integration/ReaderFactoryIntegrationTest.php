<?php

namespace MediaWiki\IPInfo\Test\Integration;

use InvalidArgumentException;
use MediaWiki\IPInfo\InfoRetriever\ReaderFactory;
use MediaWiki\Languages\LanguageFallback;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\InfoRetriever\ReaderFactory
 */
class ReaderFactoryIntegrationTest extends MediaWikiIntegrationTestCase {

	private function getFactory() {
		$languageFallback = $this->createMock( LanguageFallback::class );
		$languageFallback->method( 'getAll' )
			->willReturn( [ 'es' ] );
		return new ReaderFactory( $languageFallback );
	}

	public function testGetReaderThrowsException() {
		$this->expectException( InvalidArgumentException::class );
		$factory = $this->getFactory();
		TestingAccessWrapper::newFromObject( $factory )->getReader( '/broken/path/filename' );
	}

}
