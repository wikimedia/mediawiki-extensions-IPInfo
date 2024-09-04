<?php

namespace MediaWiki\IPInfo\Test\Integration;

use ManualLogEntry;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use RequestContext;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @covers \MediaWiki\IPInfo\TempUserIPLookup
 */
class TempUserIPLookupTest extends MediaWikiIntegrationTestCase {
	use TempUserTestTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( MainConfigNames::LogTypes, [ 'test' ] );
	}

	/**
	 * Create a log entry with the given type and parameters.
	 *
	 * @param string $type Log type
	 * @param UserIdentity $performer User who performed this logged action
	 * @return int Log ID of the newly inserted entry
	 */
	private function makeLogEntry( string $type, UserIdentity $performer ): int {
		$logEntry = new ManualLogEntry( $type, '' );
		$logEntry->setPerformer( $performer );
		$logEntry->setComment( 'test' );
		$logEntry->setTarget( new TitleValue( NS_MAIN, 'Test' ) );
		$logId = $logEntry->insert( $this->getDb() );

		$logEntry->getRecentChange( $logId )->save();

		return $logId;
	}

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

	public function testShouldReturnIPAddressDataFromLogDataOnly(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		$req = new FauxRequest();
		$req->setIP( '192.0.2.64' );

		RequestContext::getMain()->setRequest( $req );

		$user = $this->getServiceContainer()
			->getTempUserCreator()
			->create( null, $req )
			->getUser();

		$this->makeLogEntry( 'test', $user );

		$latestIP = $this->getTempUserIPLookup()->getMostRecentAddress( $user );
		$addressCount = $this->getTempUserIPLookup()->getDistinctAddressCount( $user );

		$this->assertSame( '192.0.2.64', $latestIP );
		$this->assertSame( 1, $addressCount );
	}

	public function testShouldReturnIPAddressDataFromEditsAndLogData(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		$req = new FauxRequest();
		$req->setIP( '192.0.2.64' );

		RequestContext::getMain()->setRequest( $req );

		$user = $this->getServiceContainer()
			->getTempUserCreator()
			->create( null, $req )
			->getUser();

		$this->makeLogEntry( 'test', $user );

		$page = $this->getNonexistingTestPage();

		// Make two edits, one under the same IP as used for the log entry,
		// to verify it does not get double-counted.
		$req->setIP( '192.0.2.64' );
		$this->editPage( $page, 'test2', '', NS_MAIN, $user );

		ConvertibleTimestamp::setFakeTime( wfTimestamp() + 1_000 );

		$req->setIP( '192.0.2.75' );
		$this->editPage( $page, 'test3', '', NS_MAIN, $user );

		$latestIP = $this->getTempUserIPLookup()->getMostRecentAddress( $user );
		$addressCount = $this->getTempUserIPLookup()->getDistinctAddressCount( $user );

		$this->assertSame( '192.0.2.75', $latestIP );
		$this->assertSame( 2, $addressCount );
	}
}
