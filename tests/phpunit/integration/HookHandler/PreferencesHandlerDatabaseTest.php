<?php

namespace MediaWiki\IPInfo\Test\Integration\HookHandler;

use MediaWiki\IPInfo\HookHandler\PreferencesHandler;
use MediaWiki\IPInfo\Logging\Logger;
use MediaWiki\Logging\LogEntryBase;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @group IPInfo
 * @group Database
 * @covers \MediaWiki\IPInfo\HookHandler\PreferencesHandler
 */
class PreferencesHandlerDatabaseTest extends MediaWikiIntegrationTestCase {

	public function testUsingRealPreferencesDatabase() {
		// Allows us to test that the saving of the local preference e2e works as intended
		$user = $this->getTestUser()->getUserIdentity();

		// First set the BetaFeature to be enabled, as the use agreement preference
		// cannot be enabled without it enabled.
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, 'ipinfo-beta-feature-enable', 1 );
		$userOptionsManager->saveOptions( $user );

		// Enable the use agreement preference.
		$userOptionsManager->setOption(
			$user, PreferencesHandler::IPINFO_USE_AGREEMENT, '1'
		);
		$userOptionsManager->saveOptions( $user );

		$rows = $this->newSelectQueryBuilder()
			->select( [ 'log_params' ] )
			->from( 'logging' )
			->join( 'actor', null, [ 'actor_id=log_actor' ] )
			->where( [
				'actor_name' => $user->getName(),
				'log_title' => Title::newFromText( $user->getName(), NS_USER )->getDbKey(),
				'log_action' => Logger::ACTION_CHANGE_ACCESS,
				'log_type' => Logger::LOG_TYPE,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$this->assertSame( 1, $rows->numRows() );
		$this->assertSame(
			[ '4::changeType' => Logger::ACTION_ACCESS_ENABLED ],
			LogEntryBase::extractParams( $rows->fetchRow()['log_params'] )
		);
	}
}
