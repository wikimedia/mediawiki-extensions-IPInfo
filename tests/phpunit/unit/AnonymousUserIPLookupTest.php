<?php
namespace MediaWiki\IPInfo\Test\Unit;

use MediaWiki\IPInfo\AnonymousUserIPLookup;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserIdentityUtils;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Wikimedia\Assert\ParameterAssertionException;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * @covers \MediaWiki\IPInfo\AnonymousUserIPLookup
 */
class AnonymousUserIPLookupTest extends MediaWikiUnitTestCase {
	private IConnectionProvider $connectionProvider;
	private UserIdentityUtils $userIdentityUtils;
	private ExtensionRegistry $extensionRegistry;

	private AnonymousUserIPLookup $anonymousUserIPLookup;

	protected function setUp(): void {
		parent::setUp();
		$this->connectionProvider = $this->createMock( IConnectionProvider::class );
		$this->userIdentityUtils = $this->createMock( UserIdentityUtils::class );
		$this->extensionRegistry = $this->createMock( ExtensionRegistry::class );

		$this->anonymousUserIPLookup = new AnonymousUserIPLookup(
			$this->connectionProvider,
			$this->userIdentityUtils,
			$this->extensionRegistry,
			new NullLogger()
		);
	}

	/**
	 * @dataProvider provideTestCheckIPIsKnownRejectsInvalidValues
	 */
	public function testCheckIPIsKnownRejectsInvalidValues( string $target ): void {
		$this->expectException( ParameterAssertionException::class );
		$this->expectExceptionMessage( 'Bad value for parameter $ip: must be a valid IP and cannot be a range' );
		$this->extensionRegistry->expects( $this->never() )
			->method( 'isLoaded' );

		$this->anonymousUserIPLookup->checkIPIsKnown( $target );
	}

	public static function provideTestCheckIPIsKnownRejectsInvalidValues() {
		return [
			[ 'target' => '~1' ],
			[ 'target' => 'Registered User' ],
			[ 'target' => '1.2.3.4/16' ],
		];
	}

	public function testCheckIPIsKnownNoLookupSources() {
		$this->extensionRegistry->method( 'isLoaded' )
			->willReturnMap( [
				[ 'CheckUser', '*', false ],
				[ 'AbuseFilter', '*', false ]
			] );
		$this->connectionProvider->expects( $this->never() )
			->method( 'getReplicaDatabase' );
		$isKnown = $this->anonymousUserIPLookup->checkIPIsKnown( '1.2.3.4' );
		$this->assertFalse( $isKnown );
	}
}
