<?php

namespace MediaWiki\IPInfo\Test\Unit\Rest\Handler;

use MediaWiki\IPInfo\Rest\Handler\LogHandler;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\IPInfo\Rest\Handler\LogHandler
 */
class LogHandlerTest extends MediaWikiUnitTestCase {

	use MockServiceDependenciesTrait;

	public function testBodyValidator() {
		// We cannot contract a IPInfoHandler directly as the class is abstract, so use RevisionHandler
		// which shouldn't override the body validator method.
		/** @var LogHandler $handler */
		$objectUnderTest = $this->newServiceInstance( LogHandler::class, [] );
		$bodyValidator = $objectUnderTest->getBodyValidator( 'application/json' );
		$this->assertInstanceOf(
			JsonBodyValidator::class,
			$bodyValidator
		);
	}

	public function testBodyValidatorThrowsOnUnsupportedContentType() {
		$this->expectException( LocalizedHttpException::class );
		/** @var LogHandler $handler */
		$objectUnderTest = $this->newServiceInstance( LogHandler::class, [] );
		$objectUnderTest->getBodyValidator( 'application/xml' );
	}
}
