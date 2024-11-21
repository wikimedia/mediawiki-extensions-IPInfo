<?php

namespace MediaWiki\IPInfo\Test\Integration\RestHandler;

use ArrayUtils;
use DatabaseLogEntry;
use LogPage;
use ManualLogEntry;
use MediaWiki\Context\RequestContext;
use MediaWiki\IPInfo\Info\BlockInfo;
use MediaWiki\IPInfo\Rest\Handler\LogHandler;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\Authority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Message\MessageValue;

/**
 * @group IPInfo
 * @group Database
 * @covers \MediaWiki\IPInfo\Rest\Handler\LogHandler
 *
 * The static methods in LogHandler require this test to be an integration test,
 * rather than a unit test.
 */
class LogHandlerTest extends HandlerTestCase {
	private const TEST_LOG_TYPE = 'test-log';
	private const TEST_RESTRICTED_LOG_TYPE = 'test-restricted-log';

	private const TEST_ANON_IP = '214.78.0.5';

	private const TEST_MISSING_LOG_ID = 456;

	private static Authority $blockedSysop;
	private static Authority $testSysop;
	private static Authority $ipInfoViewer;
	private static Authority $suppressedIpInfoViewer;
	private static Authority $regularUser;
	private static Authority $anonUser;

	private static int $logEntryByTempUserId;
	private static int $logEntryByAnonId;
	private static int $logEntryByAnonWithAnonTargetId;
	private static int $deletedLogEntryByAnonId;
	private static int $suppressedLogEntryByAnonId;
	private static int $logEntryByNamedUserId;
	private static int $fullyDeletedLogEntryByAnonId;
	private static int $restrictedLogEntryByAnonId;
	private static int $logEntryWithAnonTargetId;

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			MainConfigNames::LogTypes => [
				self::TEST_LOG_TYPE,
				self::TEST_RESTRICTED_LOG_TYPE
			],
			MainConfigNames::LogRestrictions => [
				self::TEST_RESTRICTED_LOG_TYPE => DefaultPresenter::IPINFO_VIEW_FULL_RIGHT
			]
		] );
	}

	protected function getHandler(): Handler {
		$services = $this->getServiceContainer();
		return LogHandler::factory(
			$services->getService( 'IPInfoInfoManager' ),
			$services->getConnectionProvider(),
			$services->getPermissionManager(),
			$services->getUserOptionsLookup(),
			$services->getUserFactory(),
			$services->getJobQueueGroup(),
			$services->getLanguageFallback(),
			$services->getUserIdentityUtils(),
			$services->getUserIdentityLookup(),
			$services->get( 'IPInfoTempUserIPLookup' )
		);
	}

	/**
	 * Convenience function to create a test request.
	 * @param int $logId ID of the log entry to fetch
	 * @param string|null $csrfToken CSRF token to pass in the request, or `null` for no token
	 * @return RequestData
	 */
	private static function getRequestData(
		int $logId = 123,
		?string $csrfToken = self::VALID_CSRF_TOKEN
	): RequestData {
		$body = $csrfToken ? json_encode( [ 'token' => $csrfToken ] ) : '';
		return new RequestData( [
			'method' => 'POST',
			'pathParams' => [ 'id' => $logId ],
			'queryParams' => [
				'dataContext' => 'infobox',
				'language' => 'en'
			],
			'headers' => [ 'Content-Type' => 'application/json' ],
			'bodyContents' => $body
		] );
	}

	/**
	 * Create a log entry with the given type and parameters.
	 *
	 * @param string $type Log type
	 * @param UserIdentity $performer User who performed this logged action
	 * @param LinkTarget $target Target of the logged action
	 * @param int|null $deleted Optional bitmask of LogPage::DELETED_* constants
	 * @return int Log ID of the newly inserted entry
	 */
	private function makeLogEntry(
		string $type,
		UserIdentity $performer,
		LinkTarget $target,
		?int $deleted = null
	): int {
		$logEntry = new ManualLogEntry( $type, '' );
		$logEntry->setPerformer( $performer );
		$logEntry->setTarget( $target );

		if ( $deleted !== null ) {
			$logEntry->setDeleted( $deleted );
		}

		$logEntry->setComment( 'test' );

		$logId = $logEntry->insert( $this->getDb() );

		$logEntry->getRecentChange( $logId )->save();

		return $logId;
	}

	public function addDBDataOnce() {
		self::$blockedSysop = $this->getTestUser( [ 'sysop' ] )->getAuthority();
		self::$testSysop = $this->getTestSysop()->getAuthority();
		self::$ipInfoViewer = $this->getTestUser( [ 'ipinfo-viewer' ] )->getAuthority();
		self::$suppressedIpInfoViewer = $this->getTestUser( [ 'ipinfo-suppressed-viewer' ] )->getAuthority();
		self::$regularUser = $this->getTestUser()->getAuthority();
		self::$anonUser = $this->getServiceContainer()->getUserFactory()->newAnonymous( self::TEST_ANON_IP );

		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				self::$blockedSysop->getUser(),
				self::$testSysop,
				'infinity'
			)
			->placeBlock();
		$this->assertStatusGood( $blockStatus, 'Block was not placed' );

		$request = new FauxRequest();
		$request->setIP( self::TEST_ANON_IP );

		RequestContext::getMain()->setRequest( $request );

		$tempUser = $this->getServiceContainer()->getTempUserCreator()
			->create( null, $request )
			->getUser();

		$anonTarget = new TitleValue( NS_USER, self::$anonUser->getName() );
		$otherTarget = new TitleValue( NS_MAIN, 'Test' );

		self::$logEntryByTempUserId = $this->makeLogEntry(
			self::TEST_LOG_TYPE,
			$tempUser,
			$otherTarget
		);

		$this->disableAutoCreateTempUser();

		self::$logEntryByAnonId = $this->makeLogEntry(
			self::TEST_LOG_TYPE,
			self::$anonUser,
			$otherTarget
		);
		self::$logEntryByNamedUserId = $this->makeLogEntry(
			self::TEST_LOG_TYPE,
			self::$regularUser->getUser(),
			$otherTarget
		);
		self::$logEntryByAnonWithAnonTargetId = $this->makeLogEntry(
			self::TEST_LOG_TYPE,
			self::$anonUser,
			$anonTarget
		);
		self::$deletedLogEntryByAnonId = $this->makeLogEntry(
			self::TEST_LOG_TYPE,
			self::$anonUser,
			$otherTarget,
			LogPage::DELETED_USER
		);
		self::$suppressedLogEntryByAnonId = $this->makeLogEntry(
			self::TEST_LOG_TYPE,
			self::$anonUser,
			$otherTarget,
			LogPage::DELETED_USER | LogPage::DELETED_RESTRICTED
		);
		self::$fullyDeletedLogEntryByAnonId = $this->makeLogEntry(
			self::TEST_LOG_TYPE,
			self::$anonUser,
			$otherTarget,
			LogPage::DELETED_USER | LogPage::DELETED_ACTION
		);
		self::$restrictedLogEntryByAnonId = $this->makeLogEntry(
			self::TEST_RESTRICTED_LOG_TYPE,
			self::$anonUser,
			$otherTarget
		);
		self::$logEntryWithAnonTargetId = $this->makeLogEntry(
			self::TEST_LOG_TYPE,
			self::$regularUser->getUser(),
			$anonTarget
		);
	}

	/**
	 * @dataProvider provideErrorCases
	 *
	 * @param callable $authorityProvider Callback to obtain the user to make the request with
	 * @param callable $logIdProvider Callback to provide the revision to fetch data for.
	 * @param string|null $csrfToken The CSRF token to send along with the request,
	 * or `null` to send no token.
	 * @param array $userOptions User options to set for the test user
	 * @param string[] $expectedError 2-tuple of [ expected error message key, expected HTTP status code ]
	 */
	public function testShouldHandleErrorCases(
		callable $authorityProvider,
		callable $logIdProvider,
		?string $csrfToken,
		array $userOptions,
		array $expectedError
	): void {
		[ $key, $code ] = $expectedError;
		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( $key ),
				$code
			)
		);

		$user = $authorityProvider();
		$user = $this->getServiceContainer()->getUserFactory()->newFromName(
			$user->getName(),
			UserFactory::RIGOR_NONE
		);

		if ( count( $userOptions ) > 0 ) {
			$this->setUserOptions( $user, $userOptions );
		}

		$request = self::getRequestData( $logIdProvider(), $csrfToken );
		$this->executeWithUser( $request, $user );
	}

	public static function provideErrorCases(): iterable {
		yield 'anonymous user without permission' => [
			fn () => self::$anonUser,
			fn () => self::$logEntryByAnonId,
			self::VALID_CSRF_TOKEN,
			[],
			[ 'ipinfo-rest-access-denied', 401 ]
		];

		yield 'regular user without permission' => [
			fn () => self::$regularUser,
			fn () => self::$logEntryByAnonId,
			self::VALID_CSRF_TOKEN,
			[],
			[ 'ipinfo-rest-access-denied', 403 ]
		];

		yield 'user with correct permissions but without accepted agreement' => [
			fn () => self::$testSysop,
			fn () => self::$logEntryByAnonId,
			self::VALID_CSRF_TOKEN,
			[ 'ipinfo-beta-feature-enable' => 1 ],
			[ 'ipinfo-rest-access-denied', 403 ]
		];

		yield 'blocked user with correct permissions and accepted agreement' => [
			fn () => self::$blockedSysop,
			fn () => self::$logEntryByAnonId,
			self::VALID_CSRF_TOKEN,
			[ 'ipinfo-beta-feature-enable' => 1, 'ipinfo-use-agreement' => 1 ],
			[ 'ipinfo-rest-access-denied', 403 ]
		];

		yield 'missing CSRF token' => [
			fn () => self::$testSysop,
			fn () => self::$logEntryByAnonId,
			null,
			[ 'ipinfo-beta-feature-enable' => 1, 'ipinfo-use-agreement' => 1 ],
			[ 'rest-badtoken-missing', 403 ]
		];

		yield 'mismatched CSRF token' => [
			fn () => self::$testSysop,
			fn () => self::$logEntryByAnonId,
			'some-bad-token',
			[ 'ipinfo-beta-feature-enable' => 1, 'ipinfo-use-agreement' => 1 ],
			[ 'rest-badtoken', 403 ]
		];

		yield 'missing log entry' => [
			fn () => self::$testSysop,
			fn () => self::TEST_MISSING_LOG_ID,
			self::VALID_CSRF_TOKEN,
			[ 'ipinfo-beta-feature-enable' => 1, 'ipinfo-use-agreement' => 1 ],
			[ 'ipinfo-rest-log-nonexistent', 404 ]
		];

		yield 'restricted log entry with user not authorized to view it' => [
			fn () => self::$testSysop,
			fn () => self::$restrictedLogEntryByAnonId,
			self::VALID_CSRF_TOKEN,
			[ 'ipinfo-beta-feature-enable' => 1, 'ipinfo-use-agreement' => 1 ],
			[ 'ipinfo-rest-log-denied', 403 ]
		];

		yield 'fully deleted log entry with user not authorized to view it' => [
			fn () => self::$ipInfoViewer,
			fn () => self::$fullyDeletedLogEntryByAnonId,
			self::VALID_CSRF_TOKEN,
			[ 'ipinfo-beta-feature-enable' => 1, 'ipinfo-use-agreement' => 1 ],
			[ 'ipinfo-rest-log-denied', 403 ]
		];

		yield 'partially deleted log entry with user not authorized to view it' => [
			fn () => self::$ipInfoViewer,
			fn () => self::$deletedLogEntryByAnonId,
			self::VALID_CSRF_TOKEN,
			[ 'ipinfo-beta-feature-enable' => 1, 'ipinfo-use-agreement' => 1 ],
			[ 'ipinfo-rest-log-registered', 404 ]
		];

		yield 'suppressed log entry with user not authorized to view it' => [
			fn () => self::$ipInfoViewer,
			fn () => self::$suppressedLogEntryByAnonId,
			self::VALID_CSRF_TOKEN,
			[ 'ipinfo-beta-feature-enable' => 1, 'ipinfo-use-agreement' => 1 ],
			[ 'ipinfo-rest-log-registered', 404 ]
		];

		yield 'log entry by named user' => [
			fn () => self::$testSysop,
			fn () => self::$logEntryByNamedUserId,
			self::VALID_CSRF_TOKEN,
			[ 'ipinfo-beta-feature-enable' => 1, 'ipinfo-use-agreement' => 1 ],
			[ 'ipinfo-rest-log-registered', 404 ]
		];
	}

	/**
	 * @dataProvider provideLogEntryCases
	 */
	public function testShouldHandleLogEntryWherePerformerOrTargetIsAnonymous(
		callable $authorityProvider,
		callable $logIdProvider,
		bool $tempUsersEnabled,
		int $expectedInfoCount
	): void {
		if ( !$tempUsersEnabled ) {
			$this->disableAutoCreateTempUser( [ 'known' => true ] );
		} else {
			$this->enableAutoCreateTempUser();
		}

		$logId = $logIdProvider();
		$logEntry = DatabaseLogEntry::newFromId( $logId, $this->getDb() );
		$performer = $logEntry->getPerformerIdentity();

		// Retrieving IP information for temporary users requires CheckUser to be installed
		if ( $this->getServiceContainer()->getUserIdentityUtils()->isTemp( $performer ) ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		}

		$user = $authorityProvider();
		$this->setUserOptions( $user, [
			'ipinfo-beta-feature-enable' => 1,
			'ipinfo-use-agreement' => 1
		] );

		$blockInfo = new BlockInfo();

		$request = self::getRequestData( $logId );
		$response = $this->executeWithUser( $request, $user );
		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertCount( $expectedInfoCount, $body['info'] );

		if ( count( $body['info'] ) === 2 ) {
			$expectedSubjects = [
				$performer->getName(),
				$logEntry->getTarget()->getText()
			];
		} elseif ( $this->getServiceContainer()->getUserIdentityUtils()->isNamed( $performer ) ) {
			$expectedSubjects = [ $logEntry->getTarget()->getText() ];
		} else {
			$expectedSubjects = [ $performer->getName() ];
		}

		$this->assertSame( 200, $response->getStatusCode() );

		foreach ( $body['info'] as $i => $item ) {
			$geoData = $item['data']['ipinfo-source-geoip2'];

			$this->assertSame( $expectedSubjects[$i], $item['subject'] );
			$this->assertSame( 'United States', $geoData['countryNames']['en'] );
			$this->assertArrayNotHasKey( 'coordinates', $geoData );

			$this->assertSame( $blockInfo->jsonSerialize(), $item['data']['ipinfo-source-block'] );

			$this->assertSame( 'ipv4', $item['data']['ipinfo-source-ipversion']['version'] );

			$contribsInfo = $item['data']['ipinfo-source-contributions'];

			$this->assertSame( 0, $contribsInfo['numLocalEdits'] );
			$this->assertSame( 0, $contribsInfo['numRecentEdits'] );

			if ( $user->isAllowed( DefaultPresenter::IPINFO_VIEW_FULL_RIGHT ) ) {
				$this->assertSame(
					[
						[ 'id' => 5391811, 'label' => 'San Diego' ],
						[ 'id' => 5332921, 'label' => 'California' ],
					],
					$geoData['location']
				);
				$this->assertSame( 721, $geoData['asn'] );
				if ( $user->isAllowed( 'deletedhistory' ) ) {
					$this->assertSame(
						0,
						$contribsInfo['numDeletedEdits']
					);
				} else {
					$this->assertArrayNotHasKey( 'numDeletedEdits', $contribsInfo );
				}
			} else {
				$this->assertArrayNotHasKey( 'asn', $geoData );
				$this->assertArrayNotHasKey( 'location', $geoData );
				$this->assertArrayNotHasKey( 'numDeletedEdits', $contribsInfo );
			}
		}
	}

	public static function provideLogEntryCases(): iterable {
		$allUsers = [
			'user with basic IPInfo access' => fn () => self::$ipInfoViewer,
			'user with IPInfo and deleted history access' => fn () => self::$testSysop
		];

		$singleResultLogEntries = [
			'log entry by temporary user' => fn () => self::$logEntryByTempUserId,
			'log entry by anonymous user' => fn () => self::$logEntryByAnonId,
			'log entry with anonymous target' => fn () => self::$logEntryWithAnonTargetId,
		];

		$logEntriesWithTwoResults = [
			'log entry by anonymous user with anonymous target' => fn () => self::$logEntryByAnonWithAnonTargetId,
		];

		$tempUserConfig = [
			'enabled' => true,
			'disabled but known' => false
		];

		yield from ArrayUtils::cartesianProduct( $allUsers, $singleResultLogEntries, $tempUserConfig, [ 1 ] );
		yield from ArrayUtils::cartesianProduct( $allUsers, $logEntriesWithTwoResults, $tempUserConfig, [ 2 ] );

		$deletedLogEntries = [
			'fully deleted log entry' => fn () => self::$fullyDeletedLogEntryByAnonId,
			'partially deleted log entry' => fn () => self::$deletedLogEntryByAnonId,
		];

		yield from ArrayUtils::cartesianProduct(
			[ fn () => self::$testSysop ],
			$deletedLogEntries,
			$tempUserConfig,
			[ 1 ]
		);

		$restrictedLogEntries = [
			'log entry with restricted log type' => fn () => self::$restrictedLogEntryByAnonId
		];

		yield from ArrayUtils::cartesianProduct(
			[ fn () => self::$ipInfoViewer ],
			$restrictedLogEntries,
			$tempUserConfig,
			[ 1 ]
		);

		$suppressedLogEntries = [
			'suppressed log entry with user authorized to view it' => fn () => self::$suppressedLogEntryByAnonId,
		];

		yield from ArrayUtils::cartesianProduct(
			[ fn () => self::$suppressedIpInfoViewer ],
			$suppressedLogEntries,
			$tempUserConfig,
			[ 1 ]
		);
	}
}
