<?php
namespace MediaWiki\IPInfo\Test\Integration\RestHandler;

use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Session\Token;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;

/**
 * Base class for IPInfo REST handler integration tests.
 */
abstract class HandlerTestCase extends MediaWikiIntegrationTestCase {
	protected const VALID_CSRF_TOKEN = 'valid-csrf-token';

	use HandlerTestTrait {
		getSession as getBaseSession;
	}
	use MockHttpTrait;
	use TempUserTestTrait;

	/** @before */
	public function ipInfoSetUp(): void {
		// Stop external extensions from affecting IPInfo by clearing the hook
		$this->clearHook( 'IPInfoHandlerRun' );

		$this->setGroupPermissions( [
			'sysop' => [
				'ipinfo' => true,
				DefaultPresenter::IPINFO_VIEW_BASIC_RIGHT => true,
				DefaultPresenter::IPINFO_VIEW_FULL_RIGHT => false,
			],
			'ipinfo-viewer' => [
				'ipinfo' => true,
				DefaultPresenter::IPINFO_VIEW_BASIC_RIGHT => true,
				DefaultPresenter::IPINFO_VIEW_FULL_RIGHT => true
			],
			'ipinfo-deleted-viewer' => [
				'ipinfo' => true,
				DefaultPresenter::IPINFO_VIEW_BASIC_RIGHT => true,
				DefaultPresenter::IPINFO_VIEW_FULL_RIGHT => true,
				'deletedhistory' => true,
			],
			'ipinfo-suppressed-viewer' => [
				'ipinfo' => true,
				DefaultPresenter::IPINFO_VIEW_BASIC_RIGHT => true,
				DefaultPresenter::IPINFO_VIEW_FULL_RIGHT => true,
				'deletedhistory' => true,
				'viewsuppressed' => true,
			]
		] );

		$this->installMockHttp(
			$this->makeFakeHttpRequest( '', 404 )
		);

		$this->overrideConfigValue(
			'IPInfoGeoLite2Prefix',
			realpath( __DIR__ . '/../../../fixtures/maxmind' ) . '/GeoLite2-'
		);
	}

	/**
	 * Set the given options for this user.
	 *
	 * @param Authority $user The user to set options for
	 * @param array $options Associative array of option names to values
	 * @return void
	 */
	protected function setUserOptions( Authority $user, array $options ): void {
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$user = $user->getUser();

		foreach ( $options as $oname => $value ) {
			$userOptionsManager->setOption( $user, $oname, $value );
		}
	}

	/**
	 * Convenience function to execute a request against the REST handler returned by
	 * {@link HandlerTestCase::getHandler}.
	 *
	 * @param RequestData $requestData Request options
	 * @param Authority $user The user to execute the request as
	 * @return ResponseInterface
	 */
	protected function executeWithUser( RequestData $requestData, Authority $user ): ResponseInterface {
		return $this->executeHandler(
			$this->getHandler(),
			$requestData,
			[],
			[],
			[],
			[],
			$user
		);
	}

	/**
	 * Set up a mock session for the REST handler with a fixed valid token value.
	 *
	 * @return \PHPUnit\Framework\MockObject\MockObject&\MediaWiki\Session\Session
	 */
	public function getSession() {
		$token = $this->createMock( Token::class );
		$token->method( 'match' )
			->willReturnCallback( fn ( $token ) => $token === self::VALID_CSRF_TOKEN );

		$session = $this->getBaseSession( false );
		$session->method( 'hasToken' )
			->willReturn( true );
		$session->method( 'getToken' )
			->willReturn( $token );

		return $session;
	}

	/**
	 * Get the REST handler to run requests against. To be overridden by subclasses.
	 * @return Handler
	 */
	abstract protected function getHandler(): Handler;
}
