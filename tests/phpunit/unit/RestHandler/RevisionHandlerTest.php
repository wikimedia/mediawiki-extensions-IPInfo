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

		$handler = new RevisionHandler(
			$this->createMock( InfoManager::class ),
			$revisionLookup,
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

		$handler = new RevisionHandler(
			$this->createMock( InfoManager::class ),
			$this->createMock( RevisionLookup::class ),
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

	public function testNoRevision() {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );
		$permissionManager->method( 'userCan' )
			->willReturn( true );

		$revisionLookup = $this->createMock( RevisionLookup::class );
		$revisionLookup->method( 'getRevisionById' )
			->willReturn( null );

		$handler = new RevisionHandler(
			$this->createMock( InfoManager::class ),
			$revisionLookup,
			$permissionManager,
			$this->createMock( UserFactory::class ),
			$this->createMock( UserIdentity::class )
		);

		$id = 123;
		$request = new RequestData( [
			'pathParams' => [ 'id' => $id ],
		] );

		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'rest-nonexistent-revision', [ $id ] ), 404 )
		);

		$this->executeHandler( $handler, $request );
	}

	public function testAccessDeniedPage() {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );
		$permissionManager->method( 'userCan' )
			->willReturn( false );

		$linkTarget = $this->createMock( LinkTarget::class );

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPageAsLinkTarget' )
			->willReturn( $linkTarget );

		$revisionLookup = $this->createMock( RevisionLookup::class );
		$revisionLookup->method( 'getRevisionById' )
			->willReturn( $revision );

		$handler = new RevisionHandler(
			$this->createMock( InfoManager::class ),
			$revisionLookup,
			$permissionManager,
			$this->createMock( UserFactory::class ),
			$this->createMock( UserIdentity::class )
		);

		$id = 123;
		$request = new RequestData( [
			'pathParams' => [ 'id' => $id ],
		] );

		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'rest-revision-permission-denied-revision', [ $id ] ), 403 )
		);

		$this->executeHandler( $handler, $request );
	}

	public function testNoAuthor() {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );
		$permissionManager->method( 'userCan' )
			->willReturn( true );

		$linkTarget = $this->createMock( LinkTarget::class );

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPageAsLinkTarget' )
			->willReturn( $linkTarget );
		$revision->method( 'getUser' )
			->willReturn( null );

		$revisionLookup = $this->createMock( RevisionLookup::class );
		$revisionLookup->method( 'getRevisionById' )
			->willReturn( $revision );

		$handler = new RevisionHandler(
			$this->createMock( InfoManager::class ),
			$revisionLookup,
			$permissionManager,
			$this->createMock( UserFactory::class ),
			$this->createMock( UserIdentity::class )
		);

		$request = new RequestData( [
			'pathParams' => [ 'id' => 123 ],
		] );

		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'ipinfo-rest-revision-no-author' ), 403 )
		);

		$this->executeHandler( $handler, $request );
	}

	public function testRegisteredAuthor() {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );
		$permissionManager->method( 'userCan' )
			->willReturn( true );

		$author = $this->createMock( UserIdentity::class, [ 'isRegistered' ] );
		$author->method( 'isRegistered' )
			->willReturn( true );

		$linkTarget = $this->createMock( LinkTarget::class );

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPageAsLinkTarget' )
			->willReturn( $linkTarget );
		$revision->method( 'getUser' )
			->willReturn( $author );

		$revisionLookup = $this->createMock( RevisionLookup::class );
		$revisionLookup->method( 'getRevisionById' )
			->willReturn( $revision );

		$handler = new RevisionHandler(
			$this->createMock( InfoManager::class ),
			$revisionLookup,
			$permissionManager,
			$this->createMock( UserFactory::class ),
			$this->createMock( UserIdentity::class )
		);

		$request = new RequestData( [
			'pathParams' => [ 'id' => 123 ],
		] );

		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'ipinfo-rest-revision-registered' ), 404 )
		);

		$this->executeHandler( $handler, $request );
	}

}
