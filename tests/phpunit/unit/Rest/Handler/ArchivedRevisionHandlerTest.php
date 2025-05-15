<?php

namespace MediaWiki\IPInfo\Test\Unit\Rest\Handler;

use MediaWiki\IPInfo\Hook\IPInfoHookRunner;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\IPInfoPermissionManager;
use MediaWiki\IPInfo\Rest\Handler\ArchivedRevisionHandler;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Revision\ArchivedRevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use MediaWikiUnitTestCase;
use Wikimedia\Message\MessageValue;
use Wikimedia\Rdbms\ReadOnlyMode;
use Wikimedia\TestingAccessWrapper;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\Rest\Handler\ArchivedRevisionHandler
 */
class ArchivedRevisionHandlerTest extends MediaWikiUnitTestCase {

	use HandlerTestTrait;

	private function getRequestData( int $id = 123 ): RequestData {
		return new RequestData( [
			'pathParams' => [ 'id' => $id ],
			'queryParams' => [
				'dataContext' => 'infobox',
				'language' => 'en'
			],
		] );
	}

	/**
	 * @dataProvider provideExecuteErrors
	 */
	public function testExecuteErrors( array $options, array $expected ) {
		$user = $this->createMock( UserIdentity::class );
		$authority = $this->createMock( Authority::class );
		$authority->method( 'getUser' )
			->willReturn( $user );
		$user->method( 'isRegistered' )
			->willReturn( $options['userIsRegistered'] ?? false );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturnMap( [
				[ $user, 'deletedhistory', $options['deletedhistory'] ],
			] );

		// Ensure other permissions checks pass...
		$ipInfoPermissionManager = $this->createMock( IPInfoPermissionManager::class );
		$ipInfoPermissionManager->method( 'canViewIPInfo' )
			->with( $authority )
			->willReturn( true );

		$permissionManager->method( 'userCan' )
			->willReturn( true );

		// Revision is mocked to not exist by returning null.
		$archivedRevisionLookup = $this->createMock( ArchivedRevisionLookup::class );
		$archivedRevisionLookup->method( 'getArchivedRevisionRecord' )
			->with( null, 123 )
			->willReturn( null );

		$handler = $this->getMockBuilder( ArchivedRevisionHandler::class )
			->setConstructorArgs( [
				'infoManager' => $this->createMock( InfoManager::class ),
				'archivedRevisionLookup' => $archivedRevisionLookup,
				'permissionManager' => $permissionManager,
				'userFactory' => $this->createMock( UserFactory::class ),
				'presenter' => $this->createMock( DefaultPresenter::class ),
				'jobQueueGroup' => $this->createMock( JobQueueGroup::class ),
				'languageFallback' => $this->createMock( LanguageFallback::class ),
				'userIdentityUtils' => $this->createMock( UserIdentityUtils::class ),
				'tempUserIPLookup' => $this->createMock( TempUserIPLookup::class ),
				'ipInfoPermissionManager' => $ipInfoPermissionManager,
				'readOnlyMode' => $this->createMock( ReadOnlyMode::class ),
				'ipInfoHookRunner' => $this->createMock( IPInfoHookRunner::class ),
			] )
			->onlyMethods( [] )
			->getMock();

		$request = $this->getRequestData();

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue(
					$expected['message'],
					$expected['messageParams'] ?? []
				),
				$expected['status']
			)
		);

		$this->executeHandler( $handler, $request, [],
			[],
			[],
			[],
			$authority );
	}

	public static function provideExecuteErrors() {
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
			]
		];
	}

	public function testGetRevisionWithExistingRevision() {
		$user = $this->createMock( UserIdentity::class );
		$authority = $this->createMock( Authority::class );
		$authority->method( 'getUser' )
			->willReturn( $user );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->with( $user, 'deletedhistory' )
			->willReturn( true );

		$revision = $this->createMock( RevisionRecord::class );

		$archivedRevisionLookup = $this->createMock( ArchivedRevisionLookup::class );
		$archivedRevisionLookup->method( 'getArchivedRevisionRecord' )
			->with( null, 123 )
			->willReturn( $revision );

		$handler = $this->getMockBuilder( ArchivedRevisionHandler::class )
			->setConstructorArgs( [
				'infoManager' => $this->createMock( InfoManager::class ),
				'archivedRevisionLookup' => $archivedRevisionLookup,
				'permissionManager' => $permissionManager,
				'userFactory' => $this->createMock( UserFactory::class ),
				'presenter' => $this->createMock( DefaultPresenter::class ),
				'jobQueueGroup' => $this->createMock( JobQueueGroup::class ),
				'languageFallback' => $this->createMock( LanguageFallback::class ),
				'userIdentityUtils' => $this->createMock( UserIdentityUtils::class ),
				'tempUserIPLookup' => $this->createMock( TempUserIPLookup::class ),
				'ipInfoPermissionManager' => $this->createMock( IPInfoPermissionManager::class ),
				'readOnlyMode' => $this->createMock( ReadOnlyMode::class ),
				'ipInfoHookRunner' => $this->createMock( IPInfoHookRunner::class ),
			] )
			->onlyMethods( [ 'getAuthority' ] )
			->getMock();
		$handler->method( 'getAuthority' )
			->willReturn( $authority );
		$handler = TestingAccessWrapper::newFromObject( $handler );

		$this->assertSame(
			$handler->getRevision( 123 ),
			$revision,
			'::getRevision did not return the expected RevisionRecord object.'
		);
	}

	public function testFactory() {
		$this->assertInstanceOf(
			ArchivedRevisionHandler::class,
			ArchivedRevisionHandler::factory(
				$this->createMock( InfoManager::class ),
				$this->createMock( ArchivedRevisionLookup::class ),
				$this->createMock( PermissionManager::class ),
				$this->createMock( UserFactory::class ),
				$this->createMock( JobQueueGroup::class ),
				$this->createMock( LanguageFallback::class ),
				$this->createMock( UserIdentityUtils::class ),
				$this->createMock( TempUserIPLookup::class ),
				$this->createMock( IPInfoPermissionManager::class ),
				$this->createMock( ReadOnlyMode::class ),
				$this->createMock( IPInfoHookRunner::class )
			)
		);
	}
}
