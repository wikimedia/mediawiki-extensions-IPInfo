<?php

namespace MediaWiki\IPInfo\Test\Integration;

use FauxRequest;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWikiIntegrationTestCase;
use RequestContext;

/**
 * @group Database
 * @covers \MediaWiki\IPInfo\TempUserIPLookup
 */
class TempUserIPLookupTest extends MediaWikiIntegrationTestCase {
	use TempUserTestTrait;

	private function getTempUserIPLookup(): TempUserIPLookup {
		return $this->getServiceContainer()->getService( 'IPInfoTempUserIPLookup' );
	}

	public function testShouldReturnIPAddressDataForAnonymousUser(): void {
		$anonUser = $this->getServiceContainer()
			->getUserFactory()
			->newAnonymous( '192.0.2.112' );

		$latestIP = $this->getTempUserIPLookup()->getMostRecentAddress( $anonUser );

		$this->assertSame( '192.0.2.112', $latestIP );
	}

	public function testShouldReturnIPAddressDataForTemporaryUser(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		$req = new FauxRequest();
		$req->setIP( '192.0.2.64' );

		RequestContext::getMain()->setRequest( $req );

		$page = $this->getNonexistingTestPage();
		$otherPage = $this->getNonexistingTestPage();

		$user = $this->getServiceContainer()
			->getTempUserCreator()
			->create( null, $req )
			->getUser();

		$this->editPage( $page, 'test', '', NS_MAIN, $user );
		$req->setIP( '192.0.2.75' );
		$this->editPage( $page, 'test2', '', NS_MAIN, $user );

		$this->editPage( $otherPage, 'test3', '', NS_MAIN, $user );

		$latestIP = $this->getTempUserIPLookup()->getMostRecentAddress( $user );
		$addressCount = $this->getTempUserIPLookup()->getDistinctAddressCount( $user );

		$this->assertSame( '192.0.2.75', $latestIP );
		$this->assertSame( 2, $addressCount );
	}
}
