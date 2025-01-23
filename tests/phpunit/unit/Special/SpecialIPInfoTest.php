<?php
namespace MediaWiki\IPInfo\Test\Unit\Special;

use MediaWiki\Config\HashConfig;
use MediaWiki\Context\IContextSource;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Special\SpecialIPInfo;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserNameUtils;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\EmptyBagOStuff;

/**
 * @covers \MediaWiki\IPInfo\Special\SpecialIPInfo
 */
class SpecialIPInfoTest extends MediaWikiUnitTestCase {
	private UserOptionsManager $userOptionsManager;

	private IContextSource $context;

	private SpecialIPInfo $page;

	protected function setUp(): void {
		parent::setUp();

		$this->userOptionsManager = $this->createMock( UserOptionsManager::class );

		$this->context = $this->createMock( IContextSource::class );

		$this->page = new SpecialIPInfo(
			$this->userOptionsManager,
			$this->createMock( UserNameUtils::class ),
			new EmptyBagOStuff(),
			$this->createMock( TempUserIPLookup::class ),
			$this->createMock( UserIdentityLookup::class ),
			$this->createMock( InfoManager::class ),
			$this->createMock( PermissionManager::class ),
			new HashConfig( [ 'IPInfoMaxDistinctIPResults' => 100 ] )
		);
		$this->page->setContext( $this->context );
	}

	/**
	 * @dataProvider provideDoesWritesData
	 * @param bool $useAgreementPref The value of the 'ipinfo-use-agreement' preference for the current user.
	 * @param bool $expected The expected return value of doesWrites().
	 */
	public function testDoesWrites( bool $useAgreementPref, bool $expected ): void {
		$user = new UserIdentityValue( 1, 'TestUser' );
		$authority = new SimpleAuthority( $user, [] );

		$this->context->method( 'getAuthority' )
			->willReturn( $authority );

		$this->userOptionsManager->method( 'getBoolOption' )
			->with( $user, 'ipinfo-use-agreement' )
			->willReturn( $useAgreementPref );

		$doesWrites = $this->page->doesWrites();

		$this->assertSame( $expected, $doesWrites );
	}

	public function provideDoesWritesData(): iterable {
		yield 'agreement accepted' => [ true, false ];
		yield 'agreement not accepted' => [ false, true ];
	}
}
