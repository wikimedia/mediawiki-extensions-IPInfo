<?php

namespace MediaWiki\IPInfo\Test\Integration\RestHandler;

use JobQueueGroup;
use LogPage;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Rest\Handler\LogHandler;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiIntegrationTestCase;
use User;
use Wikimedia\Message\MessageValue;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\Rest\Handler\LogHandler
 *
 * The static methods in LogHandler require this test to be an integration test,
 * rather than a unit test.
 */
class LogHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	/**
	 * @param array $options
	 * @return LogHandler
	 */
	private function getLogHandler( array $options = [] ): LogHandler {
		return new LogHandler( ...array_values( array_merge(
			[
				'infoManager' => $this->createMock( InfoManager::class ),
				'loadBalancer' => $this->createMock( ILoadBalancer::class ),
				'permissionManager' => $this->createMock( PermissionManager::class ),
				'userOptionsLookup' => $this->createMock( UserOptionsLookup::class ),
				'userFactory' => $this->createMock( UserFactory::class ),
				'userIdentity' => $this->createMock( UserIdentity::class ),
				'presenter' => $this->createMock( DefaultPresenter::class ),
				'jobQueueGroup' => $this->createMock( JobQueueGroup::class ),
			],
			$options
		) ) );
	}

	/**
	 * @param int $id
	 * @return RequestData
	 */
	private function getRequestData( int $id = 123 ): RequestData {
		return new RequestData( [
			'pathParams' => [ 'id' => $id ],
			'queryParams' => [ 'dataContext' => 'infobox' ],
		] );
	}

	/**
	 * @dataProvider provideExecute
	 */
	public function testExecute( $expected, $dataProperty ) {
		$id = 123;

		$db = $this->createMock( IDatabase::class );
		$db->method( 'selectRow' )
			->willReturn( [
				'logid' => $id,
				'log_type' => 'block',
				'log_deleted' => 0,
				'log_namespace' => 0,
				'log_title' => '127.0.0.2',
				'log_user' => 0,
				'log_user_text' => '127.0.0.1',
				'log_actor' => 1,
			] );

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnection' )
			->willReturn( $db );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->willReturn( true );

		$presenter = $this->createMock( DefaultPresenter::class );
		$presenter->method( 'present' )
			->willReturn( [
				'subject' => '127.0.0.2',
				'data' => [
					'provider' => [
						$dataProperty => 'testValue',
					],
				],
			] );

		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$jobQueueGroup->expects( $this->atLeastOnce() )
			->method( 'push' );

		$handler = $this->getLogHandler( [
			'loadBalancer' => $loadBalancer,
			'permissionManager' => $permissionManager,
			'userOptionsLookup' => $userOptionsLookup,
			'presenter' => $presenter,
			'jobQueueGroup' => $jobQueueGroup,
		] );

		$request = $this->getRequestData( $id );

		$response = $this->executeHandler( $handler, $request );

		$this->assertSame( 200, $response->getStatusCode() );

		$body = json_decode( $response->getBody()->getContents(), true );
		$this->assertArrayHasKey( 'info', $body );
		$this->assertIsArray( $body['info'] );
		$this->assertCount( 2, $body['info'] );

		$this->assertCount( $expected, $body['info'][0]['data']['provider'] );
	}

	public function provideExecute() {
		return [
			'Allowed property is returned' => [ 1, 'country' ],
			'Restricted property is not returned' => [ 0, 'testProperty' ],
		];
	}

	/**
	 * @dataProvider provideExecuteErrors
	 * @param array $options
	 * @param array $expected
	 */
	public function testExecuteErrors( array $options, array $expected ) {
		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnection' )
			->willReturn( $this->createMock( IDatabase::class ) );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( $options['userHasRight'] ?? null );
		$permissionManager->method( 'getUserPermissions' )
			->willReturn( [] );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->willReturn( $options['getOption'] ?? null );

		$user = $this->createMock( UserIdentity::class );
		$user->method( 'isRegistered' )
			->willReturn( $options['isRegistered'] ?? false );

		$handler = $this->getLogHandler( [
			'loadBalancer' => $loadBalancer,
			'permissionManager' => $permissionManager,
			'userOptionsLookup' => $userOptionsLookup,
			'userIdentity' => $user,
		] );

		$request = $this->getRequestData();

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
			'access denied, registered' => [
				[
					'userHasRight' => false,
					'isRegistered' => true,
				],
				[
					'message' => 'ipinfo-rest-access-denied',
					'status' => 403,
				],
			],
			'access denied, anon' => [
				[
					'userHasRight' => false,
					'isRegistered' => false,
				],
				[
					'message' => 'ipinfo-rest-access-denied',
					'status' => 401,
				],
			],
			'access denied, preference not set' => [
				[
					'userHasRight' => true,
					'getOption' => false,
					'isRegistered' => false,
				],
				[
					'message' => 'ipinfo-rest-access-denied',
					'status' => 401,
				],
			],
			'missing log' => [
				[
					'userHasRight' => true,
					'getOption' => true,
				],
				[
					'message' => 'ipinfo-rest-log-nonexistent',
					'status' => 404,
				],
			],
		];
	}

	public function testAccessDeniedLogType() {
		$id = 123;

		$db = $this->createMock( IDatabase::class );
		$db->method( 'selectRow' )
			->willReturn( [
				'logid' => $id,
				'log_type' => 'suppress',
				'log_deleted' => LogPage::DELETED_USER,
				'log_namespace' => NS_USER,
				'log_title' => '127.0.0.2',
				'log_user' => 0,
				'log_user_text' => '127.0.0.1',
				'log_actor' => 1,
			] );

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnection' )
			->willReturn( $db );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->willReturn( true );

		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromUserIdentity' )
			->willReturn( $this->getTestUser()->getUser() );

		$handler = $this->getLogHandler( [
			'loadBalancer' => $loadBalancer,
			'permissionManager' => $permissionManager,
			'userOptionsLookup' => $userOptionsLookup,
			'userFactory' => $userFactory,
		] );

		$request = $this->getRequestData( $id );

		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'ipinfo-rest-log-denied' ), 403 )
		);

		$this->executeHandler( $handler, $request );
	}

	/**
	 * @dataProvider provideTestDeletedUser
	 * @param string[] $rights
	 * @param int $deleted
	 * @param int $results
	 */
	public function testDeletedUser( array $rights, int $deleted, int $results ) {
		$id = 123;

		$this->setGroupPermissions( 'sysop', 'ipinfo', true );
		foreach ( $rights as $right => $value ) {
			$this->setGroupPermissions( 'sysop', $right, $value );
		}

		$db = $this->createMock( IDatabase::class );
		$db->method( 'selectRow' )
			->willReturn( [
				'logid' => $id,
				'log_type' => 'block',
				'log_deleted' => $deleted,
				'log_namespace' => NS_USER,
				'log_title' => '127.0.0.2',
				'log_user' => 0,
				'log_user_text' => '127.0.0.1',
				'log_actor' => 1,
			] );

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnection' )
			->willReturn( $db );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );
		$permissionManager->method( 'getUserPermissions' )
			->willReturn( [] );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->willReturn( true );

		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromUserIdentity' )
			->willReturn( $this->getTestUser( [ 'sysop' ] )->getUser() );

		$handler = $this->getLogHandler( [
			'loadBalancer' => $loadBalancer,
			'permissionManager' => $permissionManager,
			'userOptionsLookup' => $userOptionsLookup,
			'userFactory' => $userFactory,
		] );

		$request = $this->getRequestData( $id );

		$response = $this->executeHandler( $handler, $request );

		$this->assertSame( 200, $response->getStatusCode() );

		$body = json_decode( $response->getBody()->getContents(), true );
		$this->assertArrayHasKey( 'info', $body );
		$this->assertIsArray( $body['info'] );
		$this->assertCount( $results, $body['info'] );
	}

	public function provideTestDeletedUser() {
		return [
			'not allowed, only returns target' => [
				[
					'deletedhistory' => false,
					'suppressrevision' => false,
				],
				LogPage::DELETED_USER,
				1,
			],
			'allowed, returns both' => [
				[
					'deletedhistory' => true,
					'suppressrevision' => false,
				],
				LogPage::DELETED_USER,
				2,
			],
			'not allowed, only returns target (suppressed)' => [
				[
					'deletedhistory' => true,
					'suppressrevision' => false,
				],
				LogPage::DELETED_USER | LogPage::DELETED_RESTRICTED,
				1,
			],
			'allowed, returns both (suppressed)' => [
				[
					'deletedhistory' => true,
					'suppressrevision' => true,
				],
				LogPage::DELETED_USER | LogPage::DELETED_RESTRICTED,
				2,
			],
		];
	}

	public function testPerformerBlocked() {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );
		$permissionManager->method( 'getUserPermissions' )
			->willReturn( [] );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->willReturn( true );

		$userFactory = $this->createMock( UserFactory::class );
		$userFactoryUser = $this->createMock( User::class );
		$userFactoryUser->method( 'getBlock' )
			->willReturn( $this->createMock( DatabaseBlock::class ) );
		$userFactory->method( 'newFromUserIdentity' )
			->willReturn( $userFactoryUser );

		$handler = $this->getLogHandler( [
			'permissionManager' => $permissionManager,
			'userOptionsLookup' => $userOptionsLookup,
			'userFactory' => $userFactory,
		] );

		$request = $this->getRequestData();

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-access-denied-blocked-user' ),
				403
			)
		);

		$this->executeHandler( $handler, $request );
	}

	public function testPerformerRegistered() {
		$id = 123;
		$performer = $this->getTestUser()->getUser();

		$db = $this->createMock( IDatabase::class );
		$db->method( 'selectRow' )
			->willReturn( [
				'logid' => $id,
				'log_type' => 'block',
				'log_deleted' => 0,
				'log_namespace' => NS_USER,
				'log_title' => 'Test',
				'log_user' => $performer->getId(),
				'log_user_text' => $performer->getName(),
				'log_actor' => $performer->getActorId(),
			] );

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnection' )
			->willReturn( $db );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->willReturn( true );

		$handler = $this->getLogHandler( [
			'loadBalancer' => $loadBalancer,
			'permissionManager' => $permissionManager,
			'userOptionsLookup' => $userOptionsLookup,
		] );

		$request = $this->getRequestData();

		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'ipinfo-rest-log-registered' ), 404 )
		);

		$this->executeHandler( $handler, $request );
	}

	public function testDeletedTarget() {
		$id = 123;

		$performer = $this->getTestUser()->getUser();

		$db = $this->createMock( IDatabase::class );
		$db->method( 'selectRow' )
			->willReturn( [
				'logid' => $id,
				'log_type' => 'block',
				'log_deleted' => LogPage::DELETED_ACTION,
				'log_namespace' => NS_USER,
				'log_title' => '127.0.0.2',
				'log_user' => $performer->getId(),
				'log_user_text' => $performer->getName(),
				'log_actor' => $performer->getActorId(),
			] );

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnection' )
			->willReturn( $db );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->willReturn( true );

		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromUserIdentity' )
			->willReturn( $this->getTestUser()->getUser() );

		$handler = $this->getLogHandler( [
			'loadBalancer' => $loadBalancer,
			'permissionManager' => $permissionManager,
			'userOptionsLookup' => $userOptionsLookup,
			'userFactory' => $userFactory,
		] );

		$request = $this->getRequestData( $id );

		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'ipinfo-rest-log-registered' ), 404 )
		);

		$this->executeHandler( $handler, $request );
	}

	public function testDeletedTargetAllowed() {
		$id = 123;

		$performer = $this->getTestUser()->getUser();

		$db = $this->createMock( IDatabase::class );
		$db->method( 'selectRow' )
			->willReturn( [
				'logid' => $id,
				'log_type' => 'block',
				'log_deleted' => LogPage::DELETED_ACTION,
				'log_namespace' => NS_USER,
				'log_title' => '127.0.0.2',
				'log_user' => $performer->getId(),
				'log_user_text' => $performer->getName(),
				'log_actor' => $performer->getActorId(),
			] );

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnection' )
			->willReturn( $db );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );
		$permissionManager->method( 'getUserPermissions' )
			->willReturn( [] );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->willReturn( true );

		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromUserIdentity' )
			->willReturn( $this->getTestSysop()->getUser() );

		$handler = $this->getLogHandler( [
			'loadBalancer' => $loadBalancer,
			'permissionManager' => $permissionManager,
			'userOptionsLookup' => $userOptionsLookup,
			'userFactory' => $userFactory,
		] );

		$request = $this->getRequestData( $id );

		$response = $this->executeHandler( $handler, $request );

		$this->assertSame( 200, $response->getStatusCode() );

		$body = json_decode( $response->getBody()->getContents(), true );
		$this->assertArrayHasKey( 'info', $body );
		$this->assertIsArray( $body['info'] );
		$this->assertCount( 1, $body['info'] );
	}

	public function testFactory() {
		$this->assertInstanceOf(
			LogHandler::class,
			LogHandler::factory(
				$this->createMock( InfoManager::class ),
				$this->createMock( ILoadBalancer::class ),
				$this->createMock( PermissionManager::class ),
				$this->createMock( UserOptionsLookup::class ),
				$this->createMock( UserFactory::class ),
				$this->createMock( JobQueueGroup::class )
			)
		);
	}
}
