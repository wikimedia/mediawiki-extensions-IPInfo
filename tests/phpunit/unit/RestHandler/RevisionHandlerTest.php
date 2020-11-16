<?php

namespace MediaWiki\IPInfo\Test\Unit\RestHandler;

use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\RestHandler\RevisionHandler;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;
use Wikimedia\Message\MessageValue;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\RestHandler\RevisionHandler
 */
class RevisionHandlerTest extends MediaWikiUnitTestCase {

	use HandlerTestTrait;

	/**
	 * @param array $options
	 * @return RevisionHandler
	 */
	private function getRevisionHandler( array $options = [] ) : RevisionHandler {
		return new RevisionHandler( ...array_values( array_merge(
			[
				'infoManager' => $this->createMock( InfoManager::class ),
				'revisionLookup' => $this->createMock( RevisionLookup::class ),
				'permissionManager' => $this->createMock( PermissionManager::class ),
				'userFactory' => $this->createMock( UserFactory::class ),
				'userIdentity' => $this->createMock( UserIdentity::class ),
			],
			$options
		) ) );
	}

	/**
	 * @param int $id
	 * @return RequestData
	 */
	private function getRequestData( int $id = 123 ) : RequestData {
		return new RequestData( [
			'pathParams' => [ 'id' => $id ],
		] );
	}

	public function testExecute() {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );
		$permissionManager->method( 'userCan' )
			->willReturn( true );

		$author = $this->createMock( UserIdentity::class );
		$author->method( 'isRegistered' )
			->willReturn( false );
		$author->method( 'getName' )
			->willReturn( '127.0.0.1' );

		$linkTarget = $this->createMock( LinkTarget::class );

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPageAsLinkTarget' )
			->willReturn( $linkTarget );
		$revision->method( 'getUser' )
			->willReturn( $author );

		$revisionLookup = $this->createMock( RevisionLookup::class );
		$revisionLookup->method( 'getRevisionById' )
			->willReturn( $revision );

		$handler = $this->getRevisionHandler( [
			'revisionLookup' => $revisionLookup,
			'permissionManager' => $permissionManager,
		] );

		$request = $this->getRequestData();

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
			->willReturn( $options['userIsRegistered'] ?? null );
		$permissionManager->method( 'userCan' )
			->willReturn( $options['userCan'] ?? null );

		$author = $this->createMock( UserIdentity::class );
		$author->method( 'isRegistered' )
			->willReturn( $options['authorIsRegistered'] ?? null );

		$linkTarget = $this->createMock( LinkTarget::class );

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPageAsLinkTarget' )
			->willReturn( $options['getPageAsLinkTarget'] ?? $linkTarget );
		$revision->method( 'getUser' )
			->willReturn( !empty( $options['author'] ) ? $author : null );

		$revisionLookup = $this->createMock( RevisionLookup::class );
		$revisionLookup->method( 'getRevisionById' )
			->willReturn( $options['getRevisionById'] ?? $revision );

		$handler = $this->getRevisionHandler( [
			'revisionLookup' => $revisionLookup,
			'permissionManager' => $permissionManager,
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
				],
				[
					'message' => 'ipinfo-rest-access-denied',
					'status' => 403,
				],
			],
			'Access denied, anon' => [
				[
					'userIsRegistered' => false,
				],
				[
					'message' => 'ipinfo-rest-access-denied',
					'status' => 401,
				],
			],
			'No revision' => [
				[
					'id' => $id,
					'userHasRight' => true,
					'userCan' => true,
					'getRevisionById' => false,
				],
				[
					'message' => 'rest-nonexistent-revision',
					'messageParams' => [ $id ],
					'status' => 404,
				],
			],
			'Access denied page' => [
				[
					'userHasRight' => true,
					'userCan' => false,
				],
				[
					'id' => $id,
					'message' => 'rest-revision-permission-denied-revision',
					'messageParams' => [ $id ],
					'status' => 403,
				],
			],
			'No author' => [
				[
					'userHasRight' => true,
					'userCan' => true,
				],
				[
					'message' => 'ipinfo-rest-revision-no-author',
					'status' => 403,
				],
			],
			'Registered author' => [
				[
					'userHasRight' => true,
					'userCan' => true,
					'authorIsRegistered' => true,
					'author' => true,
				],
				[
					'message' => 'ipinfo-rest-revision-registered',
					'status' => 404,
				],
			],
		];
	}
}
