<?php

namespace MediaWiki\IPInfo\Test\Unit\Rest\Handler;

use JobQueueGroup;
use MediaWiki\Block\AbstractBlock;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Rest\Handler\RevisionHandler;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use MediaWikiUnitTestCase;
use Wikimedia\Message\MessageValue;
use Wikimedia\Rdbms\ReadOnlyMode;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\Rest\Handler\RevisionHandler
 * @covers \MediaWiki\IPInfo\Rest\Handler\AbstractRevisionHandler
 * @covers \MediaWiki\IPInfo\Rest\Handler\IPInfoHandler
 */
class RevisionHandlerTest extends MediaWikiUnitTestCase {

	use HandlerTestTrait;

	private function getRevisionHandler( array $options = [] ): RevisionHandler {
		return new RevisionHandler( ...array_values( array_merge(
			[
				'infoManager' => $this->createMock( InfoManager::class ),
				'revisionLookup' => $this->createMock( RevisionLookup::class ),
				'permissionManager' => $this->createMock( PermissionManager::class ),
				'userOptionsLookup' => $this->createMock( UserOptionsLookup::class ),
				'userFactory' => $this->createMock( UserFactory::class ),
				'presenter' => $this->createMock( DefaultPresenter::class ),
				'jobQueueGroup' => $this->createMock( JobQueueGroup::class ),
				'languageFallback' => $this->createMock( LanguageFallback::class ),
				'userIdentityUtils' => $this->createMock( UserIdentityUtils::class ),
				'tempUserIPLookup' => $this->createMock( TempUserIPLookup::class ),
				'extensionRegistry' => $this->createMock( ExtensionRegistry::class ),
				'readOnlyMode' => $this->createMock( ReadOnlyMode::class ),
			],
			$options
		) ) );
	}

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
	 * @dataProvider provideExecute
	 */
	public function testExecute( array $options, int $expectedCount ) {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );
		$permissionManager->method( 'userCan' )
			->willReturn( true );
		$permissionManager->method( 'getUserPermissions' )
			->willReturn( [] );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->willReturn( true );

		$presenter = $this->createMock( DefaultPresenter::class );
		$presenter->method( 'present' )
			->willReturn( [
				'subject' => '127.0.0.2',
				'data' => [
					'provider' => [
						$options['propertyName'] => 'testValue',
					],
				],
			] );

		$author = $this->createMock( UserIdentity::class );
		$author->method( 'getName' )
			->willReturn( $options['authorName'] );

		$userIdentityUtils = $this->createMock( UserIdentityUtils::class );
		$userIdentityUtils->method( 'isNamed' )
			->with( $author )
			->willReturn( false );
		$userIdentityUtils->method( 'isTemp' )
			->with( $author )
			->willReturn( $options['authorIsTemp'] );

		$linkTarget = $this->createMock( LinkTarget::class );

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPageAsLinkTarget' )
			->willReturn( $linkTarget );
		$revision->method( 'getUser' )
			->willReturn( $author );

		$revisionLookup = $this->createMock( RevisionLookup::class );
		$revisionLookup->method( 'getRevisionById' )
			->willReturn( $revision );

		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$jobQueueGroup->expects( $this->atLeastOnce() )
			->method( 'push' );

		$languageFallback = $this->createMock( LanguageFallback::class );
		$languageFallback->method( 'getAll' )
			->willReturn( [ 'en' ] );

		$handler = $this->getRevisionHandler( [
			'revisionLookup' => $revisionLookup,
			'permissionManager' => $permissionManager,
			'userOptionsLookup' => $userOptionsLookup,
			'presenter' => $presenter,
			'jobQueueGroup' => $jobQueueGroup,
			'languageFallback' => $languageFallback,
			'userIdentityUtils' => $userIdentityUtils
		] );

		$request = $this->getRequestData();

		$response = $this->executeHandler( $handler, $request );

		$this->assertSame( 200, $response->getStatusCode() );

		$body = json_decode( $response->getBody()->getContents(), true );
		$this->assertArrayHasKey( 'info', $body );
		$this->assertIsArray( $body['info'] );
		$this->assertCount( 1, $body['info'] );

		$this->assertCount( $expectedCount, $body['info'][0]['data']['provider'] );
	}

	public static function provideExecute(): iterable {
		yield 'allowed property for anonymous user' => [
			[
				'authorName' => '127.0.0.1',
				'authorIsTemp' => false,
				'propertyName' => 'country'
			],
			1
		];
		yield 'allowed property for temporary user' => [
			[
				'authorName' => '~2024-8',
				'authorIsTemp' => true,
				'propertyName' => 'country'
			],
			1
		];

		yield 'restricted property for anonymous user' => [
			[
				'authorName' => '127.0.0.1',
				'authorIsTemp' => false,
				'propertyName' => 'testProperty'
			],
			0
		];
		yield 'restricted property for temporary user' => [
			[
				'authorName' => '~2024-8',
				'authorIsTemp' => true,
				'propertyName' => 'testProperty'
			],
			0
		];
	}

	/**
	 * @dataProvider provideExecuteErrors
	 */
	public function testExecuteErrors( array $options, array $expected ) {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( $options['userHasRight'] ?? false );

		$user = $this->createMock( UserIdentity::class );
		$authority = $this->createMock( Authority::class );
		$authority->method( 'getUser' )
			->willReturn( $user );
		$user->method( 'isRegistered' )
			->willReturn( $options['userIsRegistered'] ?? false );
		$permissionManager->method( 'userCan' )
			->willReturn( $options['userCan'] ?? false );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->willReturn( $options['getOption'] ?? null );

		$author = $this->createMock( UserIdentity::class );
		$author->method( 'getName' )
			->willReturn( $options['authorName'] ?? '' );

		$userIdentityUtils = $this->createMock( UserIdentityUtils::class );
		$userIdentityUtils->method( 'isNamed' )
			->with( $author )
			->willReturn( $options['authorIsRegistered'] ?? false );
		$userIdentityUtils->method( 'isTemp' )
			->with( $author )
			->willReturn( false );

		$linkTarget = $this->createMock( LinkTarget::class );

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPageAsLinkTarget' )
			->willReturn( $options['getPageAsLinkTarget'] ?? $linkTarget );
		$revision->method( 'getUser' )
			->willReturn( !empty( $options['author'] ) ? $author : null );

		$revisionLookup = $this->createMock( RevisionLookup::class );
		$revisionLookup->method( 'getRevisionById' )
			->willReturn( isset( $options['getRevisionById'] ) ? null : $revision );

		$languageFallback = $this->createMock( LanguageFallback::class );
		$languageFallback->method( 'getAll' )
			->willReturn( [ 'en' ] );

		$handler = $this->getRevisionHandler( [
			'revisionLookup' => $revisionLookup,
			'permissionManager' => $permissionManager,
			'userOptionsLookup' => $userOptionsLookup,
			'userIdentity' => $user,
			'languageFallback' => $languageFallback,
			'userIdentityUtils' => $userIdentityUtils
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

		$this->executeHandler( $handler, $request, [],
			[],
			[],
			[],
			$authority );
	}

	public static function provideExecuteErrors() {
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
			'Access denied, preference not set' => [
				[
					'userHasRight' => true,
					'userCan' => true,
					'getOption' => false,
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
					'getOption' => true,
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
					'getOption' => true,
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
					'getOption' => true,
				],
				[
					'message' => 'ipinfo-rest-revision-no-author',
					'status' => 403,
				],
			],
			'Unregistered author and not an ip' => [
				[
					'userHasRight' => true,
					'userCan' => true,
					'getOption' => true,
					'authorIsRegistered' => false,
					'author' => true,
					'authorName' => 'foo'
				],
				[
					'message' => 'ipinfo-rest-revision-invalid-ip',
					'status' => 404,
				],
			],
			'Registered author' => [
				[
					'userHasRight' => true,
					'userCan' => true,
					'getOption' => true,
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

	public function testPerformerBlockedSitewide() {
		$user = $this->createMock( UserIdentity::class );
		$authority = $this->createMock( Authority::class );
		$authority->method( 'getUser' )
			->willReturn( $user );
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->willReturn( true );

		$block = $this->createMock( AbstractBlock::class );
		$block->method( 'isSitewide' )
			->willReturn( true );

		$userFactory = $this->createMock( UserFactory::class );
		$userFactoryUser = $this->createMock( User::class );
		$userFactoryUser->method( 'getBlock' )
			->willReturn( $block );
		$userFactory->method( 'newFromUserIdentity' )
			->willReturn( $userFactoryUser );
		$languageFallback = $this->createMock( LanguageFallback::class );
		$languageFallback->method( 'getAll' )
			->willReturn( [ 'en' ] );

		$handler = $this->getRevisionHandler( [
			'permissionManager' => $permissionManager,
			'userOptionsLookup' => $userOptionsLookup,
			'userFactory' => $userFactory,
			'languageFallback' => $languageFallback,
		] );

		$request = $this->getRequestData();

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-access-denied-blocked-user' ),
				403
			)
		);

		$this->executeHandler( $handler, $request, [],
			[],
			[],
			[],
			$authority );
	}

	public function testFactory() {
		$this->assertInstanceOf(
			RevisionHandler::class,
			RevisionHandler::factory(
				$this->createMock( InfoManager::class ),
				$this->createMock( RevisionLookup::class ),
				$this->createMock( PermissionManager::class ),
				$this->createMock( UserOptionsLookup::class ),
				$this->createMock( UserFactory::class ),
				$this->createMock( JobQueueGroup::class ),
				$this->createMock( LanguageFallback::class ),
				$this->createMock( UserIdentityUtils::class ),
				$this->createMock( TempUserIPLookup::class ),
				$this->createMock( ExtensionRegistry::class ),
				$this->createMock( ReadOnlyMode::class )
			)
		);
	}
}
