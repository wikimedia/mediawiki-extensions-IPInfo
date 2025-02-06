<?php

namespace MediaWiki\IPInfo\Test\Integration\HookHandler;

use MediaWiki\IPInfo\HookHandler\GlobalPreferencesHandler;
use MediaWiki\IPInfo\Logging\Logger;
use MediaWiki\Logging\LogEntryBase;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use MediaWiki\User\UserOptionsManager;
use MediaWikiIntegrationTestCase;

/**
 * @group IPInfo
 * @group Database
 * @covers \MediaWiki\IPInfo\HookHandler\GlobalPreferencesHandler
 */
class GlobalPreferencesHandlerDatabaseTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );

		// We don't want to test specifically the CentralAuth implementation of the CentralIdLookup. As such, force it
		// to be the local provider.
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
	}

	public function testUsingRealPreferencesDatabase() {
		// Allows us to test that UserOptionsManager::GLOBAL_CREATE works as intended, as we rely on it in user facing
		// code to set the use agreement preference.
		$user = $this->getTestUser()->getUserIdentity();

		// First set the BetaFeature to be enabled, as the use agreement preference
		// cannot be enabled without it enabled.
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, 'ipinfo-beta-feature-enable', 1 );
		$userOptionsManager->saveOptions( $user );

		// Enable the use agreement preference globally
		$userOptionsManager->setOption(
			$user, GlobalPreferencesHandler::IPINFO_USE_AGREEMENT, '1', UserOptionsManager::GLOBAL_CREATE
		);
		$userOptionsManager->saveOptions( $user );

		$rows = $this->newSelectQueryBuilder()
			->select( [ 'log_id', 'log_params' ] )
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
		$firstLogRow = $rows->fetchRow();
		$this->assertSame(
			[ '4::changeType' => Logger::ACTION_GLOBAL_ACCESS_ENABLED ],
			LogEntryBase::extractParams( $firstLogRow['log_params'] )
		);

		// Disable the feature globally.
		$userOptionsManager->setOption(
			$user, GlobalPreferencesHandler::IPINFO_USE_AGREEMENT, '0', UserOptionsManager::GLOBAL_UPDATE
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
				$this->getDb()->expr( 'log_id', '!=', $firstLogRow['log_id'] ),
			] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$this->assertSame( 1, $rows->numRows() );
		$this->assertSame(
			[ '4::changeType' => Logger::ACTION_GLOBAL_ACCESS_DISABLED ],
			LogEntryBase::extractParams( $rows->fetchRow()['log_params'] )
		);
	}
}
