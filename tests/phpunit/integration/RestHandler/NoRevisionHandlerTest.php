<?php

namespace MediaWiki\IPInfo\Test\Integration\Rest\Handler;

use MediaWiki\Context\RequestContext;
use MediaWiki\IPInfo\HookHandler\PreferencesHandler;
use MediaWiki\IPInfo\Rest\Handler\NoRevisionHandler;
use MediaWiki\IPInfo\Test\Integration\RestHandler\HandlerTestCase;
use MediaWiki\Permissions\Authority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use Wikimedia\Message\MessageValue;

/**
 * @group IPInfo
 * @group Database
 * @covers \MediaWiki\IPInfo\Rest\Handler\NoRevisionHandler
 */
class NoRevisionHandlerTest extends HandlerTestCase {
	private const TEST_ANON_IP = '214.78.0.5';

	private static Authority $sysopSuppress;
	private static Authority $ipInfoViewer;
	private static Authority $anonUser;
	private static Authority $tempUser;
	private static Authority $hiddenTempUser;
	private static Authority $tempUserNoLogs;

	protected function getHandler(): Handler {
		$services = $this->getServiceContainer();
		return NoRevisionHandler::factory(
			$services->getService( 'IPInfoInfoManager' ),
			$services->getPermissionManager(),
			$services->getUserFactory(),
			$services->getJobQueueGroup(),
			$services->getLanguageFallback(),
			$services->getUserIdentityUtils(),
			$services->get( 'IPInfoTempUserIPLookup' ),
			$services->get( 'IPInfoPermissionManager' ),
			$services->getReadOnlyMode(),
			$services->get( 'IPInfoAnonymousUserIPLookup' ),
			$services->get( 'IPInfoHookRunner' )
		);
	}

	/**
	 * Convenience function to create a test request.
	 * @param int $username
	 * @param string|null $csrfToken CSRF token to pass in the request, or `null` for no token
	 * @return RequestData
	 */
	private function getRequestData(
		string $username,
		?string $csrfToken = self::VALID_CSRF_TOKEN
	): RequestData {
		$body = $csrfToken ? json_encode( [ 'token' => $csrfToken ] ) : '';
		return new RequestData( [
			'method' => 'POST',
			'pathParams' => [ 'username' => $username ],
			'queryParams' => [
				'dataContext' => 'infobox',
				'language' => 'en'
			],
			'headers' => [ 'Content-Type' => 'application/json' ],
			'bodyContents' => $body
		] );
	}

	public function addDBDataOnce() {
		$this->enableAutoCreateTempUser();

		$request = new FauxRequest();
		$request->setIP( self::TEST_ANON_IP );
		RequestContext::getMain()->setRequest( $request );

		self::$sysopSuppress = $this->getTestUser( [ 'sysop', 'suppress' ] )->getAuthority();
		self::$ipInfoViewer = $this->getTestUser( [ 'ipinfo-viewer' ] )->getAuthority();
		self::$anonUser = $this->getServiceContainer()->getUserFactory()->newAnonymous( self::TEST_ANON_IP );

		self::$tempUser = $this->getServiceContainer()->getTempUserCreator()
			->create( null, $request )
			->getUser();
		$page = $this->getNonexistingTestPage();
		$pageUpdateStatus = $this->editPage(
			$page,
			'test',
			'',
			NS_MAIN,
			self::$tempUser
		);
		$this->assertStatusGood( $pageUpdateStatus );
		$this->deletePage( $page );

		self::$hiddenTempUser = $this->getServiceContainer()->getTempUserCreator()
			->create( null, $request )
			->getUser();
		$pageUpdateStatus = $this->editPage(
			$page,
			'test',
			'',
			NS_MAIN,
			self::$hiddenTempUser
		);
		$this->assertStatusGood( $pageUpdateStatus );
		$this->deletePage( $page );

		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				self::$hiddenTempUser->getUser(),
				self::$sysopSuppress,
				'infinity',
				'test hidden user',
				[
					'isHideUser' => true,
				]
			)
			->placeBlock();
		$this->assertStatusGood( $blockStatus, 'Block was not placed' );

		self::$tempUserNoLogs = $this->getServiceContainer()->getTempUserCreator()
			->create( null, $request )
			->getUser();

		// Delete creation log so that the account will be considered unknown
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'cu_private_event' )
			->where( [
				'cupe_title' => self::$tempUserNoLogs->getName(),
			] )
			->caller( __METHOD__ )
			->execute();

		$this->disableAutoCreateTempUser();
		$page = $this->getNonexistingTestPage();
		$pageUpdateStatus = $this->editPage(
			$page,
			'test',
			'',
			NS_MAIN,
			self::$anonUser
		);
		$this->assertStatusGood( $pageUpdateStatus );
		$this->deletePage( $page );
	}

	/**
	 * @dataProvider provideErrorCases
	 *
	 * @param callable $authorityProvider Callback to obtain the user to make the request with
	 * @param callable $usernameProvider Callback to obtain the username to target in the request.
	 * @param string|null $csrfToken The CSRF token to send along with the request,
	 * or `null` to send no token.
	 * @param string[] $expectedError 2-tuple of [ expected error message key, expected HTTP status code ]
	 */
	public function testShouldHandleErrorCases(
		callable $authorityProvider,
		callable $usernameProvider,
		?string $csrfToken,
		array $expectedError
	): void {
		[ $key, $code ] = $expectedError;
		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( $key ),
				$code
			)
		);

		$authority = $authorityProvider();
		$username = $usernameProvider();

		// User is assumed to have access to IPInfo. Access gated by these options are tested by IPInfoHandler
		$this->setUserOptions(
			$authority, [ 'ipinfo-beta-feature-enable' => 1, PreferencesHandler::IPINFO_USE_AGREEMENT => 1 ]
		);

		$request = self::getRequestData( $username, $csrfToken );
		$this->executeWithUser( $request, $authority );
	}

	public static function provideErrorCases(): iterable {
		yield 'invalid username as target' => [
			static fn () => self::$ipInfoViewer,
			static fn () => 'InvalidUser#',
			self::VALID_CSRF_TOKEN,
			[ 'rest-nonexistent-user', 404 ]
		];

		yield 'hidden temporary as target without hideuser permission' => [
			static fn () => self::$ipInfoViewer,
			static fn () => self::$hiddenTempUser->getName(),
			self::VALID_CSRF_TOKEN,
			[ 'rest-nonexistent-user', 404 ]
		];

		yield 'registered user as target' => [
			static fn () => self::$ipInfoViewer,
			static fn () => self::$ipInfoViewer->getName(),
			self::VALID_CSRF_TOKEN,
			[ 'rest-invalid-user', 403 ]
		];
	}

	/**
	 * @dataProvider provideExecuteCases
	 *
	 * @param callable $authorityProvider Callback to obtain the user to make the request with
	 * @param callable $targetProvider Callback to obtain the username to target in the request
	 */
	public function testExecute( $authorityProvider, $targetProvider ) {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		$authority = $authorityProvider();
		$target = $targetProvider();

		// User is assumed to have access to IPInfo. Access gated by these options are tested by IPInfoHandler
		$this->setUserOptions(
			$authority, [ 'ipinfo-beta-feature-enable' => 1, PreferencesHandler::IPINFO_USE_AGREEMENT => 1 ]
		);

		$request = self::getRequestData( $target, self::VALID_CSRF_TOKEN );
		$response = $this->executeWithUser( $request, $authority );
		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( $target, $body['info'][0]['subject'] );
	}

	public static function provideExecuteCases(): iterable {
		yield 'visible target with default view rights' => [
			static fn () => self::$ipInfoViewer,
			static fn () => self::$tempUser->getName(),
		];

		yield 'hidden target with hideuser rights' => [
			static fn () => self::$sysopSuppress,
			static fn () => self::$hiddenTempUser->getName(),
		];

		yield 'anonymous target with default view rights' => [
			static fn () => self::$ipInfoViewer,
			static fn () => self::TEST_ANON_IP,
		];
	}

	/**
	 * @dataProvider provideTestExecuteUnnamedUserNoLogsCases
	 *
	 * @param callable $targetProvider Callback to obtain the username to target in the request
	 */
	public function testExecuteUnnamedUserNoLogs( $targetProvider ) {
		// User is assumed to have access to IPInfo. Access gated by these options are tested by IPInfoHandler
		$authority = self::$sysopSuppress;
		$this->setUserOptions(
			$authority, [ 'ipinfo-beta-feature-enable' => 1, PreferencesHandler::IPINFO_USE_AGREEMENT => 1 ]
		);

		$request = self::getRequestData( $targetProvider(), self::VALID_CSRF_TOKEN );
		$response = $this->executeWithUser( $request, $authority );
		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( [], $body['info'] );
	}

	public static function provideTestExecuteUnnamedUserNoLogsCases(): iterable {
		yield 'temporary account with no known revisions or logs' => [
			static fn () => self::$tempUserNoLogs->getName(),
		];

		yield 'anonymous user with no known revisions or logs' => [
			static fn () => '1.2.3.4',
		];
	}

	public function testIPInfoHandlerRunHook() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		$this->setTemporaryHook( 'IPInfoHandlerRun', static function (
			string $target,
			Authority $performer,
			string $dataContext,
			array &$dataContainer
		) {
			$dataContainer['test'] = [
				'foo' => 'bar'
			];
		} );

		// User is assumed to have access to IPInfo. Access gated by these options are tested by IPInfoHandler
		$authority = self::$sysopSuppress;
		$this->setUserOptions(
			$authority, [ 'ipinfo-beta-feature-enable' => 1, PreferencesHandler::IPINFO_USE_AGREEMENT => 1 ]
		);

		$request = self::getRequestData( self::$tempUser->getName(), self::VALID_CSRF_TOKEN );
		$response = $this->executeWithUser( $request, $authority );
		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertArrayHasKey( 'test', $body['info'][0]['data'] );
		$this->assertSame( [ 'foo' => 'bar' ], $body['info'][0]['data']['test'] );
	}
}
