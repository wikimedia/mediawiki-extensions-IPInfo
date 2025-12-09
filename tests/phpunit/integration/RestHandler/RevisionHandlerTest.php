<?php

namespace MediaWiki\IPInfo\Test\Integration\Rest\Handler;

use ArrayUtils;
use MediaWiki\Block\Restriction\ActionRestriction;
use MediaWiki\Context\RequestContext;
use MediaWiki\IPInfo\HookHandler\PreferencesHandler;
use MediaWiki\IPInfo\Info\BlockInfo;
use MediaWiki\IPInfo\Rest\Handler\RevisionHandler;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\IPInfo\Test\Integration\RestHandler\HandlerTestCase;
use MediaWiki\MainConfigNames;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\Options\StaticUserOptionsLookup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\Message\MessageValue;

/**
 * @group IPInfo
 * @group Database
 * @covers \MediaWiki\IPInfo\Rest\Handler\RevisionHandler
 * @covers \MediaWiki\IPInfo\Rest\Handler\AbstractRevisionHandler
 * @covers \MediaWiki\IPInfo\Rest\Handler\IPInfoHandler
 */
class RevisionHandlerTest extends HandlerTestCase {
	private const NONEXISTENT_REV_ID = 123;
	private const TEST_ANON_IP = '214.78.0.5';

	private static Authority $blockedSysop;
	private static Authority $testSysop;
	private static Authority $regularUser;
	private static Authority $anonUser;
	private static Authority $tempUser;

	private static RevisionRecord $deletedRevRecord;

	private static RevisionRecord $revRecordByNamedUser;

	private static RevisionRecord $revRecordByAnonUser;

	private static RevisionRecord $revRecordByTempUser;

	private static RevisionRecord $revRecordForRestrictedPage;

	private static RevisionRecord $revRecordByImportedUser;

	protected function getHandler(): Handler {
		$services = $this->getServiceContainer();
		return RevisionHandler::factory(
			$services->getService( 'IPInfoInfoManager' ),
			$services->getRevisionLookup(),
			$services->getPermissionManager(),
			$services->getUserFactory(),
			$services->getJobQueueGroup(),
			$services->getLanguageFallback(),
			$services->getUserIdentityUtils(),
			$services->get( 'IPInfoTempUserIPLookup' ),
			$services->get( 'IPInfoPermissionManager' ),
			$services->getReadOnlyMode(),
			$services->getHookContainer()
		);
	}

	/**
	 * Convenience function to create a test request.
	 * @param int $revisionId ID of the revision to fetch
	 * @param string|null $csrfToken CSRF token to pass in the request, or `null` for no token
	 * @return RequestData
	 */
	private static function getRequestData(
		int $revisionId = 123,
		?string $csrfToken = self::VALID_CSRF_TOKEN
	): RequestData {
		$body = $csrfToken ? json_encode( [ 'token' => $csrfToken ] ) : '';
		return new RequestData( [
			'method' => 'POST',
			'pathParams' => [ 'id' => $revisionId ],
			'queryParams' => [
				'dataContext' => 'infobox',
				'language' => 'en'
			],
			'headers' => [ 'Content-Type' => 'application/json' ],
			'bodyContents' => $body
		] );
	}

	public function addDBDataOnce() {
		$request = new FauxRequest();
		$request->setIP( self::TEST_ANON_IP );

		RequestContext::getMain()->setRequest( $request );

		self::$blockedSysop = $this->getTestUser( [ 'sysop' ] )->getAuthority();
		self::$testSysop = $this->getTestSysop()->getAuthority();
		self::$regularUser = $this->getTestUser()->getAuthority();
		self::$anonUser = $this->getServiceContainer()->getUserFactory()->newAnonymous( self::TEST_ANON_IP );
		self::$tempUser = $this->getServiceContainer()->getTempUserCreator()
			->create( null, $request )
			->getUser();

		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				self::$blockedSysop->getUser(),
				self::$testSysop,
				'infinity'
			)
			->placeBlock();
		$this->assertStatusGood( $blockStatus, 'Block was not placed' );

		$pageUpdateStatus = $this->editPage( $this->getNonexistingTestPage(), 'test' );
		self::$deletedRevRecord = $pageUpdateStatus->getNewRevision();
		$this->revisionDelete( self::$deletedRevRecord, [
			RevisionRecord::DELETED_USER => 1,
			RevisionRecord::DELETED_RESTRICTED => 1
		] );

		$pageUpdateStatus = $this->editPage(
			$this->getNonexistingTestPage(),
			'test',
			'',
			NS_MAIN,
			self::$tempUser
		);
		self::$revRecordByTempUser = $pageUpdateStatus->getNewRevision();

		$this->disableAutoCreateTempUser();

		$pageUpdateStatus = $this->editPage(
			$this->getNonexistingTestPage(),
			'test',
			'',
			NS_MAIN,
			self::$anonUser
		);
		self::$revRecordByAnonUser = $pageUpdateStatus->getNewRevision();

		$pageUpdateStatus = $this->editPage( $this->getNonexistingTestPage(), 'test' );
		self::$revRecordForRestrictedPage = $pageUpdateStatus->getNewRevision();

		$pageUpdateStatus = $this->editPage( $this->getNonexistingTestPage(), 'test' );
		self::$revRecordByNamedUser = $pageUpdateStatus->getNewRevision();

		$importedUser = new UltimateAuthority( new UserIdentityValue( 0, 'Unknown user' ) );
		$pageUpdateStatus = $this->editPage(
			$this->getNonexistingTestPage(),
			'test',
			'',
			NS_MAIN,
			$importedUser
		);
		self::$revRecordByImportedUser = $pageUpdateStatus->getNewRevision();
	}

	public function testShouldDenyAccessForAnonymousUserIfBetaFeatureDisabled(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'BetaFeatures' );
		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-access-denied' ),
				401
			)
		);

		$user = $this->getServiceContainer()->getUserFactory()->newAnonymous();

		$request = self::getRequestData();
		$this->executeWithUser( $request, $user );
	}

	public function testShouldDenyAccessForAdminIfBetaFeatureDisabledWithoutTempAccounts(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'BetaFeatures' );
		$this->disableAutoCreateTempUser();
		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-access-denied' ),
				403
			)
		);

		$user = $this->getTestSysop()->getAuthority();
		$this->setUserOptions( $user, [
			PreferencesHandler::IPINFO_USE_AGREEMENT => 1
		] );

		$request = self::getRequestData();
		$this->executeWithUser( $request, $user );
	}

	/**
	 * @dataProvider provideErrorCases
	 *
	 * @param callable $authorityProvider Callback to obtain the user to make the request with
	 * @param callable $revRecordProvider Callback to provide the revision to fetch data for.
	 * May return `null` to indicate that a nonexistent revision ID should be looked up.
	 * @param string|null $csrfToken The CSRF token to send along with the request,
	 * or `null` to send no token.
	 * @param array $userOptions User options to set for the test user
	 * @param string[] $expectedError 2-tuple of [ expected error message key, expected HTTP status code ]
	 */
	public function testShouldHandleErrorCases(
		callable $authorityProvider,
		callable $revRecordProvider,
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
		$revRecord = $revRecordProvider();
		$revId = $revRecord ? $revRecord->getId() : self::NONEXISTENT_REV_ID;

		$this->setTemporaryHook(
			'getUserPermissionsErrors',
			static function (
				PageIdentity $checkedPage,
				Authority $checkedUser,
				string $action,
				?bool &$result
			) use ( $user ): bool {
				if ( $action === 'read' &&
					$checkedPage->isSamePageAs( self::$revRecordForRestrictedPage->getPage() ) &&
					$checkedUser->getUser()->equals( $user->getUser() ) ) {
					$result = false;
					return false;
				}
				return true;
			}
		);

		if ( count( $userOptions ) > 0 ) {
			$this->setUserOptions( $user, $userOptions );
		}

		$request = self::getRequestData( $revId, $csrfToken );
		$this->executeWithUser( $request, $user );
	}

	public static function provideErrorCases(): iterable {
		yield 'anonymous user without permission' => [
			static fn () => self::$anonUser,
			static fn () => self::$revRecordByAnonUser,
			self::VALID_CSRF_TOKEN,
			[],
			[ 'ipinfo-rest-access-denied', 401 ]
		];

		yield 'regular user without permission' => [
			static fn () => self::$regularUser,
			static fn () => self::$revRecordByAnonUser,
			self::VALID_CSRF_TOKEN,
			[],
			[ 'ipinfo-rest-access-denied', 403 ]
		];

		yield 'user with correct permissions but without accepted agreement' => [
			static fn () => self::$testSysop,
			static fn () => self::$revRecordByAnonUser,
			self::VALID_CSRF_TOKEN,
			[ 'ipinfo-beta-feature-enable' => 1 ],
			[ 'ipinfo-rest-access-denied', 403 ]
		];

		yield 'blocked user with correct permissions and accepted agreement' => [
			static fn () => self::$blockedSysop,
			static fn () => self::$revRecordByAnonUser,
			self::VALID_CSRF_TOKEN,
			[ 'ipinfo-beta-feature-enable' => 1, PreferencesHandler::IPINFO_USE_AGREEMENT => 1 ],
			[ 'ipinfo-rest-access-denied', 403 ]
		];

		yield 'missing CSRF token' => [
			static fn () => self::$testSysop,
			static fn () => self::$revRecordByAnonUser,
			null,
			[ 'ipinfo-beta-feature-enable' => 1, PreferencesHandler::IPINFO_USE_AGREEMENT => 1 ],
			[ 'rest-badtoken-missing', 403 ]
		];

		yield 'mismatched CSRF token' => [
			static fn () => self::$testSysop,
			static fn () => self::$revRecordByAnonUser,
			'some-bad-token',
			[ 'ipinfo-beta-feature-enable' => 1, PreferencesHandler::IPINFO_USE_AGREEMENT => 1 ],
			[ 'rest-badtoken', 403 ]
		];

		yield 'missing revision' => [
			static fn () => self::$testSysop,
			static fn () => null,
			self::VALID_CSRF_TOKEN,
			[ 'ipinfo-beta-feature-enable' => 1, PreferencesHandler::IPINFO_USE_AGREEMENT => 1 ],
			[ 'rest-nonexistent-revision', 404 ]
		];

		yield 'revision for restricted page' => [
			static fn () => self::$testSysop,
			static fn () => self::$revRecordForRestrictedPage,
			self::VALID_CSRF_TOKEN,
			[ 'ipinfo-beta-feature-enable' => 1, PreferencesHandler::IPINFO_USE_AGREEMENT => 1 ],
			[ 'rest-revision-permission-denied-revision', 403 ]
		];

		yield 'revision with deleted author' => [
			static fn () => self::$testSysop,
			static fn () => self::$deletedRevRecord,
			self::VALID_CSRF_TOKEN,
			[ 'ipinfo-beta-feature-enable' => 1, PreferencesHandler::IPINFO_USE_AGREEMENT => 1 ],
			[ 'ipinfo-rest-revision-no-author', 403 ]
		];

		yield 'revision with registered author' => [
			static fn () => self::$testSysop,
			static fn () => self::$revRecordByNamedUser,
			self::VALID_CSRF_TOKEN,
			[ 'ipinfo-beta-feature-enable' => 1, PreferencesHandler::IPINFO_USE_AGREEMENT => 1 ],
			[ 'ipinfo-rest-revision-registered', 404 ]
		];

		yield 'revision with imported author' => [
			static fn () => self::$testSysop,
			static fn () => self::$revRecordByImportedUser,
			self::VALID_CSRF_TOKEN,
			[ 'ipinfo-beta-feature-enable' => 1, PreferencesHandler::IPINFO_USE_AGREEMENT => 1 ],
			[ 'ipinfo-rest-revision-invalid-ip', 404 ]
		];
	}

	public function testShouldHandleSiteInReadOnlyMode() {
		$this->setService( 'UserOptionsLookup', new StaticUserOptionsLookup(
			[
				self::$testSysop->getUser()->getName() => [
					'ipinfo-beta-feature-enable' => 1,
					PreferencesHandler::IPINFO_USE_AGREEMENT => 1
				]
			]
		) );

		$request = self::getRequestData( self::$revRecordByAnonUser->getId() );

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( 'readonlytext', [ 'Maintenance' ] ),
				503
			)
		);
		$this->overrideConfigValue( MainConfigNames::ReadOnly, 'Maintenance' );
		$this->executeWithUser( $request, self::$testSysop );
	}

	/**
	 * @dataProvider provideUserGroupsAndRevisions
	 */
	public function testShouldHandleRevisionByAnonymousOrTempUser(
		array $groups,
		callable $revRecordProvider,
		bool $tempUsersEnabled
	): void {
		if ( !$tempUsersEnabled ) {
			$this->disableAutoCreateTempUser( [ 'known' => true ] );
		}

		/** @var RevisionRecord $revRecord */
		$revRecord = $revRecordProvider();

		// Retrieving IP information for temporary users requires CheckUser to be installed
		if ( $this->getServiceContainer()->getUserIdentityUtils()->isTemp( $revRecord->getUser() ) ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		}

		$user = $this->getMutableTestUser( $groups )->getAuthority();
		$this->setUserOptions( $user, [
			'ipinfo-beta-feature-enable' => 1,
			PreferencesHandler::IPINFO_USE_AGREEMENT => 1
		] );

		$blockInfo = new BlockInfo();

		$request = self::getRequestData( $revRecord->getId() );
		$response = $this->executeWithUser( $request, $user );
		$body = json_decode( $response->getBody()->getContents(), true );

		$geoData = $body['info'][0]['data']['ipinfo-source-geoip2'];

		$this->assertSame( 'ipv4', $body['info'][0]['data']['ipinfo-source-ipversion']['version'] );
		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( $revRecord->getUser()->getName(), $body['info'][0]['subject'] );
		$this->assertSame( 'United States', $geoData['countryNames']['en'] );
		$this->assertArrayNotHasKey( 'coordinates', $geoData );

		$this->assertSame( $blockInfo->jsonSerialize(), $body['info'][0]['data']['ipinfo-source-block'] );

		$contribsInfo = $body['info'][0]['data']['ipinfo-source-contributions'];

		$this->assertSame( 1, $contribsInfo['numLocalEdits'] );
		$this->assertSame( 1, $contribsInfo['numRecentEdits'] );

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

	public static function provideUserGroupsAndRevisions(): iterable {
		$groups = [
			'group with basic IPInfo access' => [ 'sysop' ],
			'group with full IPInfo access' => [ 'ipinfo-viewer' ],
			'group with full IPInfo and deleted history access' => [ 'ipinfo-deleted-viewer' ]
		];

		$revisions = [
			'revision by anonymous user' => static fn () => self::$revRecordByAnonUser,
			'revision by temporary user' => static fn () => self::$revRecordByTempUser
		];

		$tempUserConfig = [
			'enabled' => true,
			'disabled but known' => false
		];

		return ArrayUtils::cartesianProduct( $groups, $revisions, $tempUserConfig );
	}

	public function testAccessAllowedForPartiallyBlockedUser(): void {
		$performer = $this->getTestUser( [ 'sysop' ] )->getAuthority();
		$this->setUserOptions( $performer, [
			'ipinfo-beta-feature-enable' => 1,
			PreferencesHandler::IPINFO_USE_AGREEMENT => 1
		] );

		$blockStatus = $this->getServiceContainer()
			->getBlockUserFactory()
			->newBlockUser(
				$performer->getUser(),
				$this->getTestSysop()->getAuthority(),
				'infinity',
				'',
				[],
				[ new ActionRestriction( 0, 'move' ) ]
			)
			->placeBlock();
		$this->assertStatusGood( $blockStatus, 'Block was not placed' );

		$revRecord = self::$revRecordByAnonUser;

		$request = self::getRequestData( $revRecord->getId() );
		$response = $this->executeWithUser( $request, $performer );
		$body = json_decode( $response->getBody()->getContents(), true );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( $revRecord->getUser()->getName(), $body['info'][0]['subject'] );
	}
}
