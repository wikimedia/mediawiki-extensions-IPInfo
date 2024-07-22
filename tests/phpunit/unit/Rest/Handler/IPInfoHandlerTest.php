<?php

namespace MediaWiki\IPInfo\Test\Unit\Rest\Handler;

use MediaWiki\IPInfo\Rest\Handler\IPInfoHandler;
use MediaWiki\IPInfo\Rest\Handler\RevisionHandler;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\IPInfo\Rest\Handler\IPInfoHandler
 */
class IPInfoHandlerTest extends MediaWikiUnitTestCase {

	use MockServiceDependenciesTrait;
	use HandlerTestTrait;

	public function testParamSettings() {
		// We cannot contract a IPInfoHandler directly as the class is abstract, so use RevisionHandler
		/** @var IPInfoHandler $handler */
		$objectUnderTest = $this->newServiceInstance( RevisionHandler::class, [] );
		$paramSettings = $objectUnderTest->getParamSettings();
		$this->assertArrayNotHasKey( 'token', $paramSettings );

		$this->assertArrayHasKey( 'id', $paramSettings );
		$this->assertSame( 'path', $paramSettings['id'][Handler::PARAM_SOURCE] );

		$this->assertArrayHasKey( 'dataContext', $paramSettings );
		$this->assertSame( 'query', $paramSettings['dataContext'][Handler::PARAM_SOURCE] );

		$this->assertArrayHasKey( 'language', $paramSettings );
		$this->assertSame( 'query', $paramSettings['language'][Handler::PARAM_SOURCE] );
	}

	/**
	 * @dataProvider provideValidateBodyParams
	 */
	public function testValidateBodyParams( $request, $expected ) {
		// We cannot contract a IPInfoHandler directly as the class is abstract, so use RevisionHandler
		/** @var IPInfoHandler $handler */
		$handler = $this->newServiceInstance( RevisionHandler::class, [] );

		$this->initHandler( $handler, $request );
		$this->validateHandler( $handler );

		$params = $handler->getValidatedBody();
		$this->assertSame( $expected, $params );
	}

	/**
	 * @dataProvider provideValidateParams
	 */
	public function testValidateParams( $request, $expected ) {
		// We cannot contract a IPInfoHandler directly as the class is abstract, so use RevisionHandler
		/** @var IPInfoHandler $handler */
		$handler = $this->newServiceInstance( RevisionHandler::class, [] );

		$this->initHandler( $handler, $request );
		$this->validateHandler( $handler );

		$params = $handler->getValidatedParams();
		$this->assertSame( $expected, $params );
	}

	public static function provideValidateBodyParams() {
		yield 'token parameter' => [
			new RequestData( [
				'pathParams' => [ 'id' => 123 ],
				'queryParams' => [ 'dataContext' => 'context', 'language' => 'en' ],
				'parsedBody' => [ 'token' => 'kittens' ]
			] ),
			[ 'token' => 'kittens' ]
		];
	}

	public static function provideValidateParams() {
		yield 'id parameter' => [
			new RequestData( [
				'queryParams' => [ 'dataContext' => 'context', 'language' => 'en' ],
				'parsedBody' => [ 'token' => 'kittens' ],
				'pathParams' => [ 'id' => 123 ],
			] ),
			[
				'id' => 123,
				'dataContext' => 'context',
				'language' => 'en',
			]
		];
	}
}
