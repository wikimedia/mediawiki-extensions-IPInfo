<?php
namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use MediaWiki\IPInfo\InfoRetriever\IPCountInfoRetriever;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\IPInfo\InfoRetriever\IPCountInfoRetriever
 */
class IPCountInfoRetrieverTest extends MediaWikiUnitTestCase {
	private TempUserIPLookup $tempUserIPLookup;
	private IPCountInfoRetriever $retriever;

	protected function setUp(): void {
		parent::setUp();
		$this->tempUserIPLookup = $this->createMock( TempUserIPLookup::class );
		$this->retriever = new IPCountInfoRetriever( $this->tempUserIPLookup );
	}

	public function testShouldHaveValidName(): void {
		$this->assertSame( 'ipinfo-source-ip-count', $this->retriever->getName() );
	}

	public function testShouldFetchNoDataForAnonymousUser(): void {
		$user = new UserIdentityValue( 0, '127.0.0.1' );

		$this->tempUserIPLookup->expects( $this->never() )
			->method( 'getDistinctAddressCount' );

		$info = $this->retriever->retrieveFor( $user );

		$this->assertNull( $info->getCount() );
	}

	public function testShouldFetchDataForTempUser(): void {
		$user = new UserIdentityValue( 4, '~2024-8' );

		$this->tempUserIPLookup->method( 'getDistinctAddressCount' )
			->willReturn( 3 );

		$info = $this->retriever->retrieveFor( $user );

		$this->assertSame( 3, $info->getCount() );
	}
}
