<?php

namespace MediaWiki\IPInfo\Test\Unit\Rest\Handler;

use JobQueueGroup;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Rest\Handler\ArchivedRevisionHandler;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiUnitTestCase;
use Wikimedia\Message\MessageValue;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\Rest\Handler\ArchivedRevisionHandler
 */
class ArchivedRevisionHandlerTest extends MediaWikiUnitTestCase {

	use HandlerTestTrait;

	/**
	 * @param array $options
	 * @return ArchivedRevisionHandler
	 */
	private function getArchivedRevisionHandler( array $options = [] ): ArchivedRevisionHandler {
		return new ArchivedRevisionHandler( ...array_values( array_merge(
			[
				'infoManager' => $this->createMock( InfoManager::class ),
				'loadBalancer' => $this->createMock( ILoadBalancer::class ),
				'revisionStore' => $this->createMock( RevisionStore::class ),
				'permissionManager' => $this->createMock( PermissionManager::class ),
				'userOptionsLookup' => $this->createMock( UserOptionsLookup::class ),
				'userFactory' => $this->createMock( UserFactory::class ),
				'userIdentity' => $this->createMock( UserIdentity::class ),
				'presenter' => $this->createMock( DefaultPresenter::class ),
				'jobQueueGroup' => $this->createMock( JobQueueGroup::class )
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
	 * @dataProvider provideExecuteErrors
	 * @param array $options
	 * @param array $expected
	 */
	public function testExecuteErrors( array $options, array $expected ) {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'isRegistered' )
			->willReturn( $options['userIsRegistered'] ?? false );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->will( $this->returnValueMap( [
				[ $user, 'ipinfo', true ],
				[ $user, 'deletedhistory', $options['deletedhistory'] ],
			] ) );

		// Ensure other permissions checks pass...

		$permissionManager->method( 'userCan' )
			->willReturn( true );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->willReturn( true );

		$author = $this->createMock( UserIdentity::class );
		$author->method( 'isRegistered' )
			->willReturn( false );
		$author->method( 'getName' )
			->willReturn( '127.0.0.1' );

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPageAsLinkTarget' )
			->willReturn( $this->createMock( LinkTarget::class ) );
		$revision->method( 'getUser' )
			->willReturn( $author );

		$revisionStore = $this->createMock( RevisionStore::class );
		$revisionStore->method( 'newRevisionFromArchiveRow' )
			->willReturn( $revision );

		$handler = $this->getArchivedRevisionHandler( [
			'revisionStore' => $revisionStore,
			'permissionManager' => $permissionManager,
			'userOptionsLookup' => $userOptionsLookup,
			'userIdentity' => $user,
		] );

		$request = $this->getRequestData( $options['id'] ?? 123 );

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue(
					$expected['message'],
					$expected['messageParams'] ?? []
				),
				$expected['status']
			)
		);

		$this->executeHandler( $handler, $request );
	}

	public function provideExecuteErrors() {
		$id = 123;
		return [
			'Access denied, registered' => [
				[
					'userIsRegistered' => true,
					'deletedhistory' => false,
				],
				[
					'message' => 'ipinfo-rest-access-denied',
					'status' => 403,
				],
			],
			'Access denied, anon' => [
				[
					'userIsRegistered' => false,
					'deletedhistory' => false,
				],
				[
					'message' => 'ipinfo-rest-access-denied',
					'status' => 401,
				],
			],
		];
	}

	public function testFactory() {
		$this->assertInstanceOf(
			ArchivedRevisionHandler::class,
			ArchivedRevisionHandler::factory(
				$this->createMock( InfoManager::class ),
				$this->createMock( ILoadBalancer::class ),
				$this->createMock( RevisionStore::class ),
				$this->createMock( PermissionManager::class ),
				$this->createMock( UserOptionsLookup::class ),
				$this->createMock( UserFactory::class ),
				$this->createMock( JobQueueGroup::class )
			)
		);
	}
}
