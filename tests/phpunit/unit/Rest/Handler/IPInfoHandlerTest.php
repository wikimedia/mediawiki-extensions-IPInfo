<?php

namespace MediaWiki\IPInfo\Test\Unit\Rest\Handler;

use MediaWiki\IPInfo\Rest\Handler\IPInfoHandler;
use MediaWiki\IPInfo\Rest\Handler\RevisionHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\Rest\Validator\UnsupportedContentTypeBodyValidator;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\IPInfo\Rest\Handler\IPInfoHandler
 */
class IPInfoHandlerTest extends MediaWikiUnitTestCase {

	use MockServiceDependenciesTrait;

	/** @dataProvider provideBodyValidator */
	public function testBodyValidator( $contentType, $expectedBodyValidatorClassName ) {
		// We cannot contract a IPInfoHandler directly as the class is abstract, so use RevisionHandler
		// which shouldn't override the body validator method.
		/** @var IPInfoHandler $handler */
		$objectUnderTest = $this->newServiceInstance( RevisionHandler::class, [] );
		$bodyValidator = $objectUnderTest->getBodyValidator( $contentType );
		$this->assertInstanceOf(
			$expectedBodyValidatorClassName,
			$bodyValidator,
			"Expected body validator for content type $contentType to be $expectedBodyValidatorClassName"
		);
	}

	public static function provideBodyValidator() {
		return [
			'JSON content type' => [ 'application/json', JsonBodyValidator::class ],
			'Plaintext content type' => [ 'text/plain', UnsupportedContentTypeBodyValidator::class ],
			'Form data content type' => [
				'application/x-www-form-urlencoded',
				UnsupportedContentTypeBodyValidator::class
			],
		];
	}
}
