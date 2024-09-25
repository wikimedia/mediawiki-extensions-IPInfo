<?php
namespace MediaWiki\IPInfo\Test\Unit;

use DatabaseLogEntry;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentityUtils;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Wikimedia\Assert\ParameterAssertionException;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @covers \MediaWiki\IPInfo\TempUserIPLookup
 */
class TempUserIPLookupTest extends MediaWikiUnitTestCase {
	private IConnectionProvider $connectionProvider;
	private UserIdentityUtils $userIdentityUtils;
	private ExtensionRegistry $extensionRegistry;

	private TempUserIPLookup $tempUserIPLookup;

	protected function setUp(): void {
		parent::setUp();
		$this->connectionProvider = $this->createMock( IConnectionProvider::class );
		$this->userIdentityUtils = $this->createMock( UserIdentityUtils::class );
		$this->extensionRegistry = $this->createMock( ExtensionRegistry::class );

		$this->tempUserIPLookup = new TempUserIPLookup(
			$this->connectionProvider,
			$this->userIdentityUtils,
			$this->extensionRegistry,
			new NullLogger(),
			new ServiceOptions( TempUserIPLookup::CONSTRUCTOR_OPTIONS, [
				'IPInfoMaxDistinctIPResults' => 1_000
			] )
		);
	}

	/**
	 * @dataProvider provideBadConfigValues
	 */
	public function testShouldValidateConfig( $badValue ): void {
		$this->expectException( ParameterAssertionException::class );
		$this->expectExceptionMessage(
			'Bad value for parameter $serviceOptions: IPInfoMaxDistinctIPResults must be a positive integer'
		);

		new TempUserIPLookup(
			$this->connectionProvider,
			$this->userIdentityUtils,
			$this->extensionRegistry,
			new NullLogger(),
			new ServiceOptions( TempUserIPLookup::CONSTRUCTOR_OPTIONS, [
				'IPInfoMaxDistinctIPResults' => $badValue
			] )
		);
	}

	public static function provideBadConfigValues(): iterable {
		yield 'negative value' => [ -1 ];
		yield 'zero value' => [ 0 ];
		yield 'non-integer number' => [ 1.5 ];
		yield 'string' => [ 'test' ];
	}

	public function testGetMostRecentAddressShouldRejectNamedUser(): void {
		$this->expectException( ParameterAssertionException::class );
		$this->expectExceptionMessage( 'Bad value for parameter $user: must be an anonymous or temporary user' );

		$user = new UserIdentityValue( 1, 'TestUser' );

		$this->userIdentityUtils->method( 'isNamed' )
			->with( $user )
			->willReturn( true );

		$this->extensionRegistry->expects( $this->never() )
			->method( 'isLoaded' );

		$this->connectionProvider->expects( $this->never() )
			->method( 'getReplicaDatabase' );

		$this->tempUserIPLookup->getMostRecentAddress( $user );
	}

	public function testGetMostRecentAddressShouldHandleAnonymousUser(): void {
		$user = new UserIdentityValue( 0, '127.0.0.1' );

		$this->userIdentityUtils->method( 'isNamed' )
			->with( $user )
			->willReturn( false );
		$this->userIdentityUtils->method( 'isTemp' )
			->with( $user )
			->willReturn( false );

		$this->extensionRegistry->expects( $this->never() )
			->method( 'isLoaded' );

		$this->connectionProvider->expects( $this->never() )
			->method( 'getReplicaDatabase' );

		$address = $this->tempUserIPLookup->getMostRecentAddress( $user );

		$this->assertSame( $user->getName(), $address );
	}

	public function testGetMostRecentAddressShouldHandleTemporaryUserIfCheckUserDisabled(): void {
		$user = new UserIdentityValue( 5, '~2024-8' );

		$this->userIdentityUtils->method( 'isNamed' )
			->with( $user )
			->willReturn( false );
		$this->userIdentityUtils->method( 'isTemp' )
			->with( $user )
			->willReturn( true );

		$this->extensionRegistry->method( 'isLoaded' )
			->with( 'CheckUser' )
			->willReturn( false );

		$this->connectionProvider->expects( $this->never() )
			->method( 'getReplicaDatabase' );

		$address = $this->tempUserIPLookup->getMostRecentAddress( $user );

		$this->assertNull( $address );
	}

	/**
	 * @dataProvider provideMostRecentAddressLookupResults
	 */
	public function testGetMostRecentAddressShouldCacheLookupsForTemporaryUser(
		$result
	): void {
		$user = new UserIdentityValue( 5, '~2024-8' );

		$this->userIdentityUtils->method( 'isNamed' )
			->with( $user )
			->willReturn( false );
		$this->userIdentityUtils->method( 'isTemp' )
			->with( $user )
			->willReturn( true );

		$this->extensionRegistry->method( 'isLoaded' )
			->with( 'CheckUser' )
			->willReturn( true );

		$selectQueryBuilder = $this->createMock( SelectQueryBuilder::class );
		$selectQueryBuilder->method( $this->logicalNot( $this->equalTo( 'fetchRow' ) ) )
			->willReturnSelf();

		$queryResults = [
			$result ? (object)[ 'cuc_timestamp' => wfTimestampNow(), 'cuc_ip' => $result ] : false,
			false
		];
		$selectQueryBuilder->expects( $this->exactly( 2 ) )
			->method( 'fetchRow' )
			->willReturnCallback( static function () use ( &$queryResults ) {
				return array_shift( $queryResults );
			} );

		$dbr = $this->createMock( IReadableDatabase::class );
		$dbr->method( 'newSelectQueryBuilder' )
			->willReturn( $selectQueryBuilder );

		$this->connectionProvider->expects( $this->once() )
			->method( 'getReplicaDatabase' )
			->willReturn( $dbr );

		$address = $this->tempUserIPLookup->getMostRecentAddress( $user );
		$secondLookup = $this->tempUserIPLookup->getMostRecentAddress( $user );

		$expected = $result ?: null;

		$this->assertSame( $expected, $address );
		$this->assertSame( $expected, $secondLookup );
	}

	public static function provideMostRecentAddressLookupResults(): iterable {
		yield 'valid IP' => [ '192.0.2.7' ];
		yield 'missing data' => [ false ];
	}

	public function testGetMostRecentAddressShouldVaryCachePerUser(): void {
		$user = new UserIdentityValue( 5, '~2024-8' );
		$otherUser = new UserIdentityValue( 6, '~2024-9' );

		$eitherUser = $this->logicalOr(
			$this->equalTo( $user ),
			$this->equalTo( $otherUser )
		);

		$this->userIdentityUtils->method( 'isNamed' )
			->with( $eitherUser )
			->willReturn( false );
		$this->userIdentityUtils->method( 'isTemp' )
			->with( $eitherUser )
			->willReturn( true );

		$this->extensionRegistry->method( 'isLoaded' )
			->with( 'CheckUser' )
			->willReturn( true );

		$selectQueryBuilder = $this->createMock( SelectQueryBuilder::class );
		$selectQueryBuilder->method( $this->logicalNot( $this->equalTo( 'fetchRow' ) ) )
			->willReturnSelf();

		$queryResults = [
			(object)[ 'cuc_timestamp' => wfTimestampNow(), 'cuc_ip' => '192.0.2.7' ],
			false,
			(object)[ 'cuc_timestamp' => wfTimestampNow(), 'cuc_ip' => '192.0.2.85' ],
			false
		];
		$selectQueryBuilder->expects( $this->exactly( 4 ) )
			->method( 'fetchRow' )
			->willReturnCallback( static function () use ( &$queryResults ) {
				return array_shift( $queryResults );
			} );

		$dbr = $this->createMock( IReadableDatabase::class );
		$dbr->method( 'newSelectQueryBuilder' )
			->willReturn( $selectQueryBuilder );

		$this->connectionProvider->expects( $this->exactly( 2 ) )
			->method( 'getReplicaDatabase' )
			->willReturn( $dbr );

		$firstUserAddress = $this->tempUserIPLookup->getMostRecentAddress( $user );
		$otherUserAddress = $this->tempUserIPLookup->getMostRecentAddress( $otherUser );

		$this->assertSame( '192.0.2.7', $firstUserAddress );
		$this->assertSame( '192.0.2.85', $otherUserAddress );
	}

	/**
	 * @dataProvider provideEvents
	 */
	public function testShouldPreferMoreRecentEvent( array $queryResults ): void {
		$user = new UserIdentityValue( 5, '~2024-8' );

		$this->userIdentityUtils->method( 'isNamed' )
			->with( $user )
			->willReturn( false );
		$this->userIdentityUtils->method( 'isTemp' )
			->with( $user )
			->willReturn( true );

		$this->extensionRegistry->method( 'isLoaded' )
			->with( 'CheckUser' )
			->willReturn( true );

		$selectQueryBuilder = $this->createMock( SelectQueryBuilder::class );
		$selectQueryBuilder->method( $this->logicalNot( $this->equalTo( 'fetchRow' ) ) )
			->willReturnSelf();

		$selectQueryBuilder->expects( $this->exactly( 2 ) )
			->method( 'fetchRow' )
			->willReturnCallback( static function () use ( &$queryResults ) {
				return array_shift( $queryResults );
			} );

		$dbr = $this->createMock( IReadableDatabase::class );
		$dbr->method( 'newSelectQueryBuilder' )
			->willReturn( $selectQueryBuilder );

		$this->connectionProvider->expects( $this->once() )
			->method( 'getReplicaDatabase' )
			->willReturn( $dbr );

		$address = $this->tempUserIPLookup->getMostRecentAddress( $user );

		$this->assertSame( '192.0.2.85', $address );
	}

	public static function provideEvents(): iterable {
		yield 'no data in cu_changes' => [
			[
				false,
				(object)[ 'cule_timestamp' => wfTimestampNow(), 'cule_ip' => '192.0.2.85' ],
			]
		];

		yield 'more recent data in cu_changes' => [
			[
				(object)[ 'cuc_timestamp' => wfTimestampNow(), 'cuc_ip' => '192.0.2.85' ],
				(object)[ 'cule_timestamp' => wfTimestamp( TS_MW, wfTimestamp() - 1_000 ), 'cule_ip' => '192.0.2.7' ],
			]
		];

		yield 'more recent data in cu_log_events' => [
			[
				(object)[ 'cuc_timestamp' => wfTimestamp( TS_MW, wfTimestamp() - 1_000 ), 'cuc_ip' => '192.0.2.7' ],
				(object)[ 'cule_timestamp' => wfTimestampNow(), 'cule_ip' => '192.0.2.85' ],
			]
		];
	}

	public function testGetDistinctAddressCountShouldRejectNonTempUser(): void {
		$this->expectException( ParameterAssertionException::class );
		$this->expectExceptionMessage( 'Bad value for parameter $user: must be a temporary user' );

		$user = new UserIdentityValue( 1, 'TestUser' );

		$this->userIdentityUtils->method( 'isTemp' )
			->with( $user )
			->willReturn( false );

		$this->extensionRegistry->expects( $this->never() )
			->method( 'isLoaded' );

		$this->connectionProvider->expects( $this->never() )
			->method( 'getReplicaDatabase' );

		$this->tempUserIPLookup->getDistinctAddressCount( $user );
	}

	public function testGetDistinctAddressCountShouldHandleTemporaryUserIfCheckUserDisabled(): void {
		$user = new UserIdentityValue( 5, '~2024-8' );

		$this->userIdentityUtils->method( 'isTemp' )
			->with( $user )
			->willReturn( true );

		$this->extensionRegistry->method( 'isLoaded' )
			->with( 'CheckUser' )
			->willReturn( false );

		$this->connectionProvider->expects( $this->never() )
			->method( 'getReplicaDatabase' );

		$count = $this->tempUserIPLookup->getDistinctAddressCount( $user );

		$this->assertNull( $count );
	}

	public function testGetAddressForRevisionShouldRejectNamedUser(): void {
		$this->expectException( ParameterAssertionException::class );
		$this->expectExceptionMessage(
			'Bad value for parameter $revision: must be authored by an anonymous or temporary user'
		);

		$user = new UserIdentityValue( 1, 'TestUser' );

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getUser' )
			->with( $revision::RAW )
			->willReturn( $user );

		$this->userIdentityUtils->method( 'isNamed' )
			->with( $user )
			->willReturn( true );

		$this->extensionRegistry->expects( $this->never() )
			->method( 'isLoaded' );

		$this->connectionProvider->expects( $this->never() )
			->method( 'getReplicaDatabase' );

		$this->tempUserIPLookup->getAddressForRevision( $revision );
	}

	public function testGetAddressForRevisionShouldHandleAnonymousUser(): void {
		$user = new UserIdentityValue( 0, '127.0.0.1' );

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getUser' )
			->with( $revision::RAW )
			->willReturn( $user );

		$this->userIdentityUtils->method( 'isNamed' )
			->with( $user )
			->willReturn( false );

		$this->userIdentityUtils->method( 'isTemp' )
			->with( $user )
			->willReturn( false );

		$this->extensionRegistry->expects( $this->never() )
			->method( 'isLoaded' );

		$this->connectionProvider->expects( $this->never() )
			->method( 'getReplicaDatabase' );

		$address = $this->tempUserIPLookup->getAddressForRevision( $revision );

		$this->assertSame( $user->getName(), $address );
	}

	public function testGetAddressForRevisionShouldHandleTemporaryUserIfCheckUserDisabled(): void {
		$user = new UserIdentityValue( 1, '~2024-8' );

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getUser' )
			->with( $revision::RAW )
			->willReturn( $user );

		$this->userIdentityUtils->method( 'isNamed' )
			->with( $user )
			->willReturn( false );

		$this->userIdentityUtils->method( 'isTemp' )
			->with( $user )
			->willReturn( true );

		$address = $this->tempUserIPLookup->getAddressForRevision( $revision );

		$this->assertNull( $address );
	}

	public function testGetDistinctIPInfoShouldRejectNonTempUser(): void {
		$this->expectException( ParameterAssertionException::class );
		$this->expectExceptionMessage( 'Bad value for parameter $user: must be a temporary user' );

		$user = new UserIdentityValue( 1, 'TestUser' );

		$this->userIdentityUtils->method( 'isTemp' )
			->with( $user )
			->willReturn( false );

		$this->extensionRegistry->expects( $this->never() )
			->method( 'isLoaded' );

		$this->connectionProvider->expects( $this->never() )
			->method( 'getReplicaDatabase' );

		$this->tempUserIPLookup->getDistinctIPInfo( $user );
	}

	public function testGetDistinctIPInfoShouldHandleTemporaryUserIfCheckUserDisabled(): void {
		$user = new UserIdentityValue( 5, '~2024-8' );

		$this->userIdentityUtils->method( 'isTemp' )
			->with( $user )
			->willReturn( true );

		$this->extensionRegistry->method( 'isLoaded' )
			->with( 'CheckUser' )
			->willReturn( false );

		$this->connectionProvider->expects( $this->never() )
			->method( 'getReplicaDatabase' );

		$records = $this->tempUserIPLookup->getDistinctIPInfo( $user );

		$this->assertSame( [], $records );
	}

	public function testGetAddressForLogEntryShouldRejectNamedUser(): void {
		$this->expectException( ParameterAssertionException::class );
		$this->expectExceptionMessage(
			'Bad value for parameter $logEntry: performer must be an anonymous or temporary user'
		);

		$user = new UserIdentityValue( 1, 'TestUser' );

		$logEntry = $this->createMock( DatabaseLogEntry::class );
		$logEntry->method( 'getPerformerIdentity' )
			->willReturn( $user );

		$this->userIdentityUtils->method( 'isNamed' )
			->with( $user )
			->willReturn( true );

		$this->extensionRegistry->expects( $this->never() )
			->method( 'isLoaded' );

		$this->connectionProvider->expects( $this->never() )
			->method( 'getReplicaDatabase' );

		$this->tempUserIPLookup->getAddressForLogEntry( $logEntry );
	}

	public function testGetAddressForLogEntryShouldHandleAnonymousUser(): void {
		$user = new UserIdentityValue( 0, '127.0.0.1' );

		$logEntry = $this->createMock( DatabaseLogEntry::class );
		$logEntry->method( 'getPerformerIdentity' )
			->willReturn( $user );

		$this->userIdentityUtils->method( 'isNamed' )
			->with( $user )
			->willReturn( false );
		$this->userIdentityUtils->method( 'isTemp' )
			->with( $user )
			->willReturn( false );

		$this->extensionRegistry->expects( $this->never() )
			->method( 'isLoaded' );

		$this->connectionProvider->expects( $this->never() )
			->method( 'getReplicaDatabase' );

		$address = $this->tempUserIPLookup->getAddressForLogEntry( $logEntry );

		$this->assertSame( $user->getName(), $address );
	}

	public function testGetAddressForLogEntryShouldHandleTemporaryUserIfCheckUserDisabled(): void {
		$user = new UserIdentityValue( 1, '~2024-8' );

		$logEntry = $this->createMock( DatabaseLogEntry::class );
		$logEntry->method( 'getPerformerIdentity' )
			->willReturn( $user );

		$this->userIdentityUtils->method( 'isNamed' )
			->with( $user )
			->willReturn( false );
		$this->userIdentityUtils->method( 'isTemp' )
			->with( $user )
			->willReturn( true );

		$this->extensionRegistry->method( 'isLoaded' )
			->with( 'CheckUser' )
			->willReturn( false );

		$this->connectionProvider->expects( $this->never() )
			->method( 'getReplicaDatabase' );

		$address = $this->tempUserIPLookup->getAddressForLogEntry( $logEntry );

		$this->assertNull( $address );
	}
}
