<?php

namespace MediaWiki\IPInfo\Test\Integration;

use MediaWiki\IPInfo\AnonymousUserIPLookup;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Wikimedia\IPUtils;

/**
 * @group Database
 * @covers \MediaWiki\IPInfo\AnonymousUserIPLookup
 */
class AnonymousUserIPLookupTest extends MediaWikiIntegrationTestCase {
	use TempUserTestTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
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

	private function getAnonymousUserIPLookup(): AnonymousUserIPLookup {
		return $this->getServiceContainer()->getService( 'IPInfoAnonymousUserIPLookup' );
	}

	public function testCheckIPIsKnownUsesNormalizedIPAddress() {
		$this->disableAutoCreateTempUser();
		$anonUser = $this->getServiceContainer()
			->getUserFactory()
			->newAnonymous( '2001:0001:0001:0001:0001:0001:0001:0001' );
		$page = $this->getNonexistingTestPage();
		$this->editPage( $page, 'test', '', NS_MAIN, $anonUser );

		// Assert that the IP is known after the edit
		$this->assertTrue(
			$this->getAnonymousUserIPLookup()->checkIPIsKnown( '2001:1:1:1:1:1:1:1' )
		);

		// Assert that using an unsanitized version of the IP returns results as expected
		$this->assertTrue(
			$this->getAnonymousUserIPLookup()->checkIPIsKnown( '2001:0001:0001:0001:0001:0001:0001:0001' )
		);
	}

	public function testCheckIPIsKnownNotKnown() {
		$anonUser = $this->getServiceContainer()
			->getUserFactory()
			->newAnonymous( '1.2.3.4' );
		$this->assertFalse(
			$this->getAnonymousUserIPLookup()->checkIPIsKnown( '1.2.3.4' )
		);
	}

	public function testCheckIPIsKnownCUChangesLookup() {
		$this->disableAutoCreateTempUser();
		$anonUser = $this->getServiceContainer()
			->getUserFactory()
			->newAnonymous( '1.2.3.4' );
		$page = $this->getNonexistingTestPage();
		$this->editPage( $page, 'test', '', NS_MAIN, $anonUser );
		$this->assertTrue(
			$this->getAnonymousUserIPLookup()->checkIPIsKnown( '1.2.3.4' )
		);
	}

	public function testCheckIPIsKnownCULogsLookup() {
		$this->disableAutoCreateTempUser();
		$anonUser = $this->getServiceContainer()
			->getUserFactory()
			->newAnonymous( '1.2.3.4' );
		$this->makeLogEntry( 'test', $anonUser );
		$this->assertTrue(
			$this->getAnonymousUserIPLookup()->checkIPIsKnown( '1.2.3.4' )
		);
	}

	public function testCheckIPIsKnownCUPrivateEventsLookup() {
		$this->disableAutoCreateTempUser();
		$anonUser = $this->getServiceContainer()
			->getUserFactory()
			->newAnonymous( '1.2.3.4' );

		// Stub out a private event associated with the anonymous user
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_private_event' )
			->row( [
				'cupe_id' => 1,
				'cupe_namespace' => 0,
				'cupe_title' => 'Main_Page',
				'cupe_actor' => null,
				'cupe_log_type' => 'abusefilter',
				'cupe_log_action' => 'hit',
				'cupe_params' => '',
				'cupe_comment_id' => 1,
				'cupe_page' => 1,
				'cupe_timestamp' => $this->getDb()->timestamp(),
				'cupe_ip' => '1.2.3.4',
			] )
			->caller( __METHOD__ )
			->execute();

		$this->assertTrue(
			$this->getAnonymousUserIPLookup()->checkIPIsKnown( '1.2.3.4' )
		);
	}

	public function testPreTemporaryAccountsAnonymousUserIsKnownCUPrivateEventsLookup() {
		// Create an anonymous user with an associated actor id
		$this->disableAutoCreateTempUser();
		$anonUser = $this->getServiceContainer()
			->getUserFactory()
			->newAnonymous( '1.2.3.5' );
		$actorId = $this->getServiceContainer()
			->getActorStore()
			->acquireActorId( $anonUser, $this->getDb() );

		// Stub out a private event associated with the anonymous user where the actor id is null
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_private_event' )
			->row( [
				'cupe_id' => 1,
				'cupe_namespace' => 0,
				'cupe_title' => 'Main_Page',
				'cupe_actor' => null,
				'cupe_log_type' => 'abusefilter',
				'cupe_log_action' => 'hit',
				'cupe_params' => '',
				'cupe_comment_id' => 1,
				'cupe_page' => 1,
				'cupe_timestamp' => $this->getDb()->timestamp(),
				'cupe_ip' => '1.2.3.5',
			] )
			->caller( __METHOD__ )
			->execute();

		$this->assertTrue(
			$this->getAnonymousUserIPLookup()->checkIPIsKnown( '1.2.3.5' )
		);
	}

	public function testCheckIPIsKnownAFLookup() {
		$this->markTestSkippedIfExtensionNotLoaded( 'Abuse Filter' );

		$this->disableAutoCreateTempUser();
		$anonUser = $this->getServiceContainer()
			->getUserFactory()
			->newAnonymous( '1.2.3.4' );

		// Stub out an abuse filter hit to represent a blocked action associated with the anonymous user
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'abuse_filter_log' )
			->row( [
				'afl_global' => 0,
				'afl_filter_id' => 1,
				'afl_user' => 0,
				'afl_user_text' => '1.2.3.4',
				'afl_ip_hex' => IPUtils::toHex( '1.2.3.4' ),
				'afl_action' => 'edit',
				'afl_actions' => 'disallow',
				'afl_var_dump' => 'tt:1',
				'afl_timestamp' => $this->getDb()->timestamp(),
				'afl_namespace' => 0,
				'afl_title' => 'Main Page'
			] )
			->caller( __METHOD__ )
			->execute();

		$this->assertTrue(
			$this->getAnonymousUserIPLookup()->checkIPIsKnown( '1.2.3.4' )
		);
	}
}
