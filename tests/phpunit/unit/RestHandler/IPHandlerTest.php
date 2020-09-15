<?php

namespace MediaWiki\IPInfo\Test\Unit\RestHandler;

use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\RestHandler\IPHandler;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;
use Wikimedia\Message\MessageValue;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\RestHandler\IPHandler
 */
class IPHandlerTest extends MediaWikiUnitTestCase {

	use HandlerTestTrait;

	/**
	 * @param string $ip
	 * @return RequestInterface
	 */
	private function createRequest( string $ip ) : RequestInterface {
		return new RequestData( [
			'method' => 'POST',
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'bodyContents' => json_encode( [ 'ip' => $ip ] ),
		] );
	}

	public function testExecute() {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );

		$handler = new IPHandler(
			$this->createMock( InfoManager::class ),
			$permissionManager,
			$this->createMock( UserIdentity::class )
		);

		$request = $this->createRequest( '127.0.0.1' );

		$response = $this->executeHandler( $handler, $request );

		$this->assertSame( 200, $response->getStatusCode() );
	}

	public function testInvalidIP() {
		$handler = new IPHandler(
			$this->createMock( InfoManager::class ),
			$this->createMock( PermissionManager::class ),
			$this->createMock( UserIdentity::class )
		);

		$request = $this->createRequest( 'invalid' );

		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'ipinfo-rest-ip-invalid' ), 400 )
		);

		$this->executeHandler( $handler, $request );
	}

	/**
	 * @dataProvider provideAccessDenied
	 * @param bool $isRegistered
	 * @param int $httpStatus
	 */
	public function testAccessDenied( bool $isRegistered, int $httpStatus ) {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( false );

		$user = $this->createMock( UserIdentity::class );
		$user->method( 'isRegistered' )
			->willReturn( $isRegistered );

		$handler = new IPHandler(
			$this->createMock( InfoManager::class ),
			$permissionManager,
			$user
		);

		$request = $this->createRequest( '127.0.0.1' );

		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'ipinfo-rest-access-denied' ), $httpStatus )
		);

		$this->executeHandler( $handler, $request );
	}

	public function provideAccessDenied() {
		return [
			'registered user throws a 403' => [
				true,
				403,
			],
			'anon user throws a 401' => [
				false,
				401
			]
		];
	}

}
