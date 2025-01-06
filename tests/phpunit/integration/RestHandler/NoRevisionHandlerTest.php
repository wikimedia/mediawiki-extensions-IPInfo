<?php

namespace MediaWiki\IPInfo\Test\Integration\Rest\Handler;

use MediaWiki\Context\RequestContext;
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
	private static Authority $tempUser;
	private static Authority $hiddenTempUser;

	protected function getHandler(): Handler {
		$services = $this->getServiceContainer();
		return NoRevisionHandler::factory(
			$services->getService( 'IPInfoInfoManager' ),
			$services->getPermissionManager(),
			$services->getUserOptionsLookup(),
			$services->getUserFactory(),
			$services->getJobQueueGroup(),
			$services->getLanguageFallback(),
			$services->getUserIdentityUtils(),
			$services->get( 'IPInfoTempUserIPLookup' ),
			$services->getExtensionRegistry()
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
		$this->setUserOptions( $authority, [ 'ipinfo-beta-feature-enable' => 1, 'ipinfo-use-agreement' => 1 ] );

		$request = self::getRequestData( $username, $csrfToken );
		$this->executeWithUser( $request, $authority );
	}

	public static function provideErrorCases(): iterable {
		yield 'anonymous user as target' => [
			static fn () => self::$ipInfoViewer,
			fn () => self::TEST_ANON_IP,
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
		$authority = $authorityProvider();
		$target = $targetProvider();

		// User is assumed to have access to IPInfo. Access gated by these options are tested by IPInfoHandler
		$this->setUserOptions( $authority, [ 'ipinfo-beta-feature-enable' => 1, 'ipinfo-use-agreement' => 1 ] );

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
	}
}
