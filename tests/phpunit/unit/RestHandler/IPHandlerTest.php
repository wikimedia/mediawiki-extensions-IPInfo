<?php

namespace MediaWiki\IPInfo\Test\Unit\RestHandler;

use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\RestHandler\IPHandler;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
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
	 * @param array $options
	 * @return IPHandler
	 */
	private function getIPHandler( array $options = [] ) : IPHandler {
		return new IPHandler( ...array_values( array_merge(
			[
				'infoManager' => $this->createMock( InfoManager::class ),
				'permissionManager' => $this->createMock( PermissionManager::class ),
				'userIdentity' => $this->createMock( UserIdentity::class ),
			],
			$options
		) ) );
	}

	/**
	 * @param string $ip
	 * @return RequestData
	 */
	private function getRequestData( string $ip ) : RequestData {
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

		$handler = $this->getIPHandler( [
			'permissionManager' => $permissionManager,
		] );

		$request = $this->getRequestData( '127.0.0.1' );

		$response = $this->executeHandler( $handler, $request );

		$this->assertSame( 200, $response->getStatusCode() );
	}

	/**
	 * @dataProvider provideExecuteErrors
	 * @param array $options
	 * @param array $expected
	 */
	public function testExecuteErrors( array $options, array $expected ) {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( $options['userHasRight'] ?? null );

		$user = $this->createMock( UserIdentity::class );
		$user->method( 'isRegistered' )
			->willReturn( $options['isRegistered'] ?? null );

		$handler = $this->getIPHandler( [
			'permissionManager' => $permissionManager,
			'userIdentity' => $user,
		] );

		$request = $this->getRequestData( $options['ip'] );

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( $expected['message'] ),
				$expected['status']
			)
		);

		$this->executeHandler( $handler, $request );
	}

	public function provideExecuteErrors() {
		return [
			'IP invalid' => [
				[
					'ip' => 'invalid',
				],
				[
					'message' => 'ipinfo-rest-ip-invalid',
					'status' => 400,
				]
			],
			'access denied, registered user throws a 403' => [
				[
					'ip' => '127.0.0.1',
					'isRegistered' => true,
				],
				[
					'message' => 'ipinfo-rest-access-denied',
					'status' => 403,
				]
			],
			'access denied, anon user throws a 401' => [
				[
					'ip' => '127.0.0.1',
					'isRegistered' => false,
				],
				[
					'message' => 'ipinfo-rest-access-denied',
					'status' => 401,
				]
			]
		];
	}

}
