<?php
namespace MediaWiki\IPInfo\Test\Unit;

use ExtensionRegistry;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\User\UserIdentityUtils;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
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
			$this->extensionRegistry
		);
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
		$selectQueryBuilder->method( $this->logicalNot( $this->equalTo( 'fetchField' ) ) )
			->willReturnSelf();
		$selectQueryBuilder->expects( $this->once() )
			->method( 'fetchField' )
			->willReturn( $result );

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

		$ips = [ '192.0.2.7', '192.0.2.85' ];

		$selectQueryBuilder = $this->createMock( SelectQueryBuilder::class );
		$selectQueryBuilder->method( $this->logicalNot( $this->equalTo( 'fetchField' ) ) )
			->willReturnSelf();
		$selectQueryBuilder->expects( $this->exactly( 2 ) )
			->method( 'fetchField' )
			->willReturnCallback( static function () use ( &$ips ) {
				return array_shift( $ips );
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
}
