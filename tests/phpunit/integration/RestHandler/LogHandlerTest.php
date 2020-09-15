<?php

namespace MediaWiki\IPInfo\Test\Integration\RestHandler;

use LogPage;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\RestHandler\LogHandler;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Wikimedia\Message\MessageValue;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\RestHandler\LogHandler
 *
 * The static methods in LogHandler require this test to be an integration test,
 * rather than a unit test.
 */
class LogHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	public function testExecute() {
		$db = $this->createMock( IDatabase::class );
		$db->method( 'selectRow' )
			->willReturn( [
				'logid' => 123,
				'log_deleted' => 0,
				'log_user' => 0,
				'log_user_text' => '127.0.0.1',
			] );

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnection' )
			->willReturn( $db );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );

		$handler = new LogHandler(
			$this->createMock( InfoManager::class ),
			$loadBalancer,
			$permissionManager,
			$this->createMock( UserFactory::class ),
			$this->createMock( UserIdentity::class )
		);

		$request = new RequestData( [
			'pathParams' => [ 'id' => 123 ],
		] );

		$response = $this->executeHandler( $handler, $request );

		$this->assertSame( 200, $response->getStatusCode() );
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

		$handler = new LogHandler(
			$this->createMock( InfoManager::class ),
			$this->createMock( ILoadBalancer::class ),
			$permissionManager,
			$this->createMock( UserFactory::class ),
			$user
		);

		$request = new RequestData( [
			'pathParams' => [ 'id' => 123 ],
		] );

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

	public function testMissingLog() {
		$db = $this->createMock( IDatabase::class );
		$db->method( 'selectRow' )
			->willReturn( null );

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnection' )
			->willReturn( $db );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );

		$handler = new LogHandler(
			$this->createMock( InfoManager::class ),
			$loadBalancer,
			$permissionManager,
			$this->createMock( UserFactory::class ),
			$this->createMock( UserIdentity::class )
		);

		$request = new RequestData( [
			'pathParams' => [ 'id' => 123 ],
		] );

		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'ipinfo-rest-log-nonexistent' ), 404 )
		);

		$this->executeHandler( $handler, $request );
	}

	public function testLogSuppressed() {
		$db = $this->createMock( IDatabase::class );
		$db->method( 'selectRow' )
			->willReturn( [
				'logid' => 123,
				'log_deleted' => LogPage::DELETED_USER,
				'log_user' => 0,
				'log_user_text' => '127.0.0.1',
			] );

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnection' )
			->willReturn( $db );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );

		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromUserIdentity' )
			->willReturn( $this->getTestUser()->getUser() );

		$handler = new LogHandler(
			$this->createMock( InfoManager::class ),
			$loadBalancer,
			$permissionManager,
			$userFactory,
			$this->createMock( UserIdentity::class )
		);

		$request = new RequestData( [
			'pathParams' => [ 'id' => 123 ],
		] );

		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'ipinfo-rest-log-denied' ), 403 )
		);

		$this->executeHandler( $handler, $request );
	}

	public function testPerformerRegistered() {
		$performer = $this->getTestUser()->getUser();

		$db = $this->createMock( IDatabase::class );
		$db->method( 'selectRow' )
			->willReturn( [
				'logid' => 123,
				'log_deleted' => 0,
				'log_user' => $performer->getId(),
				'log_user_text' => $performer->getName(),
			] );

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnection' )
			->willReturn( $db );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );

		$handler = new LogHandler(
			$this->createMock( InfoManager::class ),
			$loadBalancer,
			$permissionManager,
			$this->createMock( UserFactory::class ),
			$this->createMock( UserIdentity::class )
		);

		$request = new RequestData( [
			'pathParams' => [ 'id' => 123 ],
		] );

		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'ipinfo-rest-log-registered' ), 404 )
		);

		$this->executeHandler( $handler, $request );
	}

}
