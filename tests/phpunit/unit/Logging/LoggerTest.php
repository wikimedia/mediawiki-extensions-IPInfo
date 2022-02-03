<?php

namespace MediaWiki\IPInfo\Test\Unit\Logging;

use Generator;
use IDatabase;
use ManualLogEntry;
use MediaWiki\IPInfo\Logging\Logger;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Title;

/**
 * @coversDefaultClass \MediaWiki\IPInfo\Logging\Logger
 */
class LoggerTest extends MediaWikiUnitTestCase {
	public function provideLogViewInfobox(): Generator {
		yield [ 'isDebounced' => true ];
		yield [ 'isDebounced' => false ];
	}

	/**
	 * @dataProvider provideLogViewInfobox
	 * @covers ::logViewInfobox
	 * @covers ::log
	 */
	public function testLogViewInfobox( bool $isDebounced ): void {
		$performer = new UserIdentityValue( 1, 'Foo' );
		$actorId = 2;
		$target = '127.0.0.1';

		$expectedTarget = Title::makeTitle( NS_USER, $target );
		$expectedParams = [ '4::level' => 'ipinfo-view-full' ];

		$database = $this->createMock( IDatabase::class );

		// We don't need to stub IDatabase::timestamp() since it is wrapped in
		// a call to IDatabase::addQuotes().
		$database->method( 'addQuotes' )
			->willReturn( 42 );

		$database->method( 'buildLike' )
			->willReturn( ' LIKE \'%ipinfo-view-full%\'' );

		$database->expects( $this->once() )
			->method( 'selectRowCount' )
			->with(
				'logging',
				'*',
				[
					'log_type' => Logger::LOG_TYPE,
					'log_action' => Logger::ACTION_VIEW_INFOBOX,
					'log_actor' => $actorId,
					'log_namespace' => NS_USER,
					'log_title' => $target,
					'log_timestamp > 42',
					'log_params LIKE \'%ipinfo-view-full%\'',
				]
			)
			->willReturn( (int)$isDebounced );

		$actorStore = $this->createMock( ActorStore::class );
		$actorStore->method( 'acquireActorId' )
			->will(
				$this->returnValueMap( [
					[ $performer, $database, $actorId ],
				] )
			);

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'getUserPermissions' )
			->will(
				$this->returnValueMap( [
					[ $performer, [ 'ipinfo-view-basic', 'ipinfo-view-full' ] ],
				] )
			);

		$logger = $this->getMockBuilder( Logger::class )
			->setConstructorArgs( [
				$actorStore,
				$permissionManager,
				$database,
				24 * 60 * 60,
			] )
			->onlyMethods( [ 'createManualLogEntry' ] )
			->getMock();

		if ( $isDebounced ) {
			$logger->expects( $this->never() )
				->method( 'createManualLogEntry' );
		} else {
			$logEntry = $this->createMock( ManualLogEntry::class );
			$logEntry->expects( $this->once() )
				->method( 'setPerformer' )
				->with( $performer );

			$logEntry->expects( $this->once() )
				->method( 'setTarget' )
				->with( $expectedTarget );

			$logEntry->expects( $this->once() )
				->method( 'setParameters' )
				->with( $expectedParams );

			$logEntry->expects( $this->once() )
				->method( 'insert' )
				->with( $database );

			$logger->expects( $this->once() )
				->method( 'createManualLogEntry' )
				->with( Logger::ACTION_VIEW_INFOBOX )
				->willReturn( $logEntry );
		}

		$logger->logViewInfobox( $performer, $target );
	}
}
