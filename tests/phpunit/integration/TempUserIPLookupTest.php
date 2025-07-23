<?php

namespace MediaWiki\IPInfo\Test\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\IPInfo\TempUserIPRecord;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Wikimedia\IPUtils;
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

		// Set a timestamp in the past for account creation so that
		// the timestamps for this and the revision later don't conflict
		ConvertibleTimestamp::setFakeTime( '20240101000000' );
		$user = $this->getServiceContainer()
			->getTempUserCreator()
			->create( null, $req )
			->getUser();
		ConvertibleTimestamp::setFakeTime( false );

		$status = $this->editPage( $page, 'test', '', NS_MAIN, $user );
		$firstRev = $status->getNewRevision();
		$req->setIP( '192.0.2.75' );
		$this->editPage( $page, 'test2', '', NS_MAIN, $user );

		$this->editPage( $otherPage, 'test3', '', NS_MAIN, $user );

		$latestIP = $this->getTempUserIPLookup()->getMostRecentAddress( $user );
		$addressCount = $this->getTempUserIPLookup()->getDistinctAddressCount( $user );
		$firstRevIP = $this->getTempUserIPLookup()->getAddressForRevision( $firstRev );

		$this->assertSame( '192.0.2.75', $latestIP );
		$this->assertSame( 2, $addressCount );
		$this->assertSame( '192.0.2.64', $firstRevIP );
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

		$logId = $this->makeLogEntry( 'test', $user );
		$logEntry = DatabaseLogEntry::newFromId( $logId, $this->getDb() );

		$page = $this->getNonexistingTestPage();

		// Make two edits, one under the same IP as used for the log entry,
		// to verify it does not get double-counted.
		$req->setIP( '192.0.2.64' );
		$this->editPage( $page, 'test2', '', NS_MAIN, $user );

		ConvertibleTimestamp::setFakeTime( wfTimestamp() + 1_000 );

		$req->setIP( '192.0.2.75' );
		$secondEdit = $this->editPage( $page, 'test3', '', NS_MAIN, $user );

		$latestIP = $this->getTempUserIPLookup()->getMostRecentAddress( $user );
		$addressCount = $this->getTempUserIPLookup()->getDistinctAddressCount( $user );
		$logIP = $this->getTempUserIPLookup()->getAddressForLogEntry( $logEntry );
		$distinctAddressRecords = $this->getTempUserIPLookup()->getDistinctIPInfo( $user );

		$this->assertSame( '192.0.2.75', $latestIP );
		$this->assertSame( 2, $addressCount );
		$this->assertSame( '192.0.2.64', $logIP );
		$this->assertEquals(
			[
				'192.0.2.64' => new TempUserIPRecord( '192.0.2.64', null, $logId ),
				'192.0.2.75' => new TempUserIPRecord( '192.0.2.75', $secondEdit->getNewRevision()->getId(), null ),
			],
			$distinctAddressRecords
		);
	}

	public function testShouldReturnCheckUserLookupIP() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		$this->enableAutoCreateTempUser();

		$request = new FauxRequest();
		$request->setIP( '1.2.3.4' );
		RequestContext::getMain()->setRequest( $request );
		$tempUserWithDeletedRevisions = $this->getServiceContainer()->getTempUserCreator()
			->create( '~2025-01', $request )
			->getUser();
		$page = $this->getNonexistingTestPage();
		$pageUpdateStatus = $this->editPage(
			$page,
			'test',
			'',
			NS_MAIN,
			$tempUserWithDeletedRevisions
		);
		$this->deletePage( $page );
		$latestIP = $this->getTempUserIPLookup()->getMostRecentAddress( $tempUserWithDeletedRevisions );
		$this->assertSame( '1.2.3.4', $latestIP );
	}

	public function testShouldReturnAbuseFilterLookupIP() {
		$this->markTestSkippedIfExtensionNotLoaded( 'Abuse Filter' );
		$this->enableAutoCreateTempUser();

		$actorIP = '5.6.7.8';
		$request = new FauxRequest();
		$request->setIP( $actorIP );
		RequestContext::getMain()->setRequest( $request );
		$tempUserWithBlockedActions = $this->getServiceContainer()->getTempUserCreator()
			->create( '~2025-02', $request )
			->getUser();

		// Stub out an abuse filter hit to represent a blocked action associated with the temp account
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'abuse_filter_log' )
			->row( [
				'afl_global' => 0,
				'afl_filter_id' => 1,
				'afl_user' => 1,
				'afl_user_text' => $tempUserWithBlockedActions->getName(),
				// afl_ip still needs to be written; don't use $actorIP to verify it's not read from
				'afl_ip' => '',
				'afl_ip_hex' => IPUtils::toHex( $actorIP ),
				'afl_action' => 'edit',
				'afl_actions' => 'disallow',
				'afl_var_dump' => 'tt:1',
				'afl_timestamp' => $this->getDb()->timestamp(),
				'afl_namespace' => 0,
				'afl_title' => 'Main Page'
			] )
			->caller( __METHOD__ )
			->execute();

		$latestIP = $this->getTempUserIPLookup()->getMostRecentAddress( $tempUserWithBlockedActions );
		$this->assertSame( $actorIP, $latestIP );
	}

	public function testShouldReturnLatestCheckUserAbuseFilterLookupIP() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		$this->markTestSkippedIfExtensionNotLoaded( 'Abuse Filter' );
		$this->enableAutoCreateTempUser();

		$actorIP = '10.11.12.13';
		$request = new FauxRequest();
		$request->setIP( $actorIP );
		RequestContext::getMain()->setRequest( $request );
		$tempUserWithMultipleBlockedActions = $this->getServiceContainer()->getTempUserCreator()
			->create( '~2025-03', $request )
			->getUser();

		ConvertibleTimestamp::setFakeTime( '20240101000000' );
		$page = $this->getNonexistingTestPage();
		$pageUpdateStatus = $this->editPage(
			$page,
			'test',
			'',
			NS_MAIN,
			$tempUserWithMultipleBlockedActions
		);
		$this->deletePage( $page );
		ConvertibleTimestamp::setFakeTime( false );

		// Stub out an abuse filter hit to represent a blocked action associated with the temp account
		// Set the timestamp for this action in the past and use a different IP than the other action
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'abuse_filter_log' )
			->row( [
				'afl_global' => 0,
				'afl_filter_id' => 1,
				'afl_user' => 1,
				'afl_user_text' => $tempUserWithMultipleBlockedActions->getName(),
				// afl_ip still needs to be written; don't use 1.2.3.4 to verify it's not read from
				'afl_ip' => '',
				'afl_ip_hex' => IPUtils::toHex( '1.2.3.4' ),
				'afl_action' => 'edit',
				'afl_actions' => 'disallow',
				'afl_var_dump' => 'tt:1',
				'afl_timestamp' => $this->getDb()->timestamp( '20000101000000' ),
				'afl_namespace' => 0,
				'afl_title' => 'Main Page'
			] )
			->caller( __METHOD__ )
			->execute();

		// Assert that the timestamp from the CU log takes priority over the AF log and returns the later IP
		$latestIP = $this->getTempUserIPLookup()->getMostRecentAddress( $tempUserWithMultipleBlockedActions );
		$this->assertSame( $actorIP, $latestIP );
	}
}
