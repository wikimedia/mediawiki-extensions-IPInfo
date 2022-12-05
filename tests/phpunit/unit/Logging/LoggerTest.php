<?php

namespace MediaWiki\IPInfo\Test\Unit\Logging;

use Generator;
use ManualLogEntry;
use MediaWiki\IPInfo\Logging\Logger;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Title;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @covers \MediaWiki\IPInfo\Logging\Logger
 */
class LoggerTest extends MediaWikiUnitTestCase {
	public function provideLogViewDebounced(): Generator {
		yield [
			'logMethod' => 'logViewInfobox',
			'logAction' => Logger::ACTION_VIEW_INFOBOX,
			'isDebounced' => true,
		];
		yield [
			'logMethod' => 'logViewPopup',
			'logAction' => Logger::ACTION_VIEW_POPUP,
			'isDebounced' => false,
		];
	}

	/**
	 * @dataProvider provideLogViewDebounced
	 */
	public function testLogViewDebounced(
		string $logMethod,
		string $action,
		bool $isDebounced
	): void {
		$performer = new UserIdentityValue( 1, 'Foo' );
		$actorId = 2;
		$target = '127.0.0.1';

		$expectedTarget = Title::makeTitle( NS_USER, $target );
		$expectedParams = [ '4::level' => 'ipinfo-view-full' ];

		$database = $this->createMock( IDatabase::class );

		$queryBuilder = new SelectQueryBuilder( $database );

		$database->method( 'newSelectQueryBuilder' )
			->willReturn( $queryBuilder );

		// We don't need to stub IDatabase::timestamp() since it is wrapped in
		// a call to IDatabase::addQuotes().
		$database->method( 'addQuotes' )
			->willReturn( 42 );

		$database->method( 'buildLike' )
			->willReturn( ' LIKE \'%ipinfo-view-full%\'' );

		$map = [
				[
					[ 'logging' ],
					[ '*' ],
					[
						"log_type" => "ipinfo",
						"log_action" => Logger::ACTION_VIEW_INFOBOX,
						"log_actor" => 2,
						"log_namespace" => 2,
						"log_title" => "127.0.0.1",
						0 => "log_timestamp > 42",
						1 => "log_params LIKE '%ipinfo-view-full%'",
					],
					SelectQueryBuilder::class,
					[],
					[],
					(int)$isDebounced
				]
				,
				[
					[ 'logging' ],
					[ '*' ],
					[
						"log_type" => "ipinfos",
						"log_action" => Logger::ACTION_VIEW_INFOBOX,
						"log_actor" => 2,
						"log_namespace" => 2,
						"log_title" => "127.0.0.1",
						0 => "log_timestamp > 42",
						1 => "log_params LIKE '%ipinfo-view-full%'",
					],
					SelectQueryBuilder::class,
					[],
					[],
					(int)$isDebounced
				]
		];

		$database->method( 'selectRow' )
			->willReturnMap( $map );

		$actorStore = $this->createMock( ActorStore::class );
		$actorStore->method( 'findActorId' )
			->willReturnMap(
				[ [ $performer, $database, $actorId ], ]
			);

		$logger = $this->getMockBuilder( Logger::class )
			->setConstructorArgs( [
				$actorStore,
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
				->with( $action )
				->willReturn( $logEntry );
		}

		$logger->$logMethod(
			$performer,
			$target,
			(int)wfTimestamp(),
			$expectedParams['4::level']
		);
	}

	public function provideTestLogViewNoLevel(): Generator {
		yield [ 'logMethod' => 'logViewInfobox' ];
		yield [ 'logMethod' => 'logViewPopup' ];
	}

	/**
	 * @dataProvider provideTestLogViewNoLevel
	 */
	public function testLogViewNoLevel( string $logMethod ): void {
		$logger = new Logger(
			$this->createMock( ActorStore::class ),
			$this->createMock( IDatabase::class ),
			24 * 60 * 60
		);
		$this->assertNull(
			$logger->$logMethod(
				$this->createMock( UserIdentityValue::class ),
				'1.2.3.4',
				31556926,
				null
			)
		);
	}

	public function provideLogAccess(): Generator {
		yield [
			'logMethod' => 'logAccessEnabled',
			'changeType' => Logger::ACTION_ACCESS_ENABLED
		];
		yield [
			'logMethod' => 'logAccessDisabled',
			'changeType' => Logger::ACTION_ACCESS_DISABLED
		];
	}

	/**
	 * @dataProvider provideLogAccess
	 */
	public function testLogAccess( $logMethod, $changeType ) {
		$name = 'Foo';
		$performer = new UserIdentityValue( 1, $name );
		$expectedTarget = Title::makeTitle( NS_USER, $name );

		$expectedParams = [ '4::changeType' => $changeType ];

		$database = $this->createMock( IDatabase::class );

		$logger = $this->getMockBuilder( Logger::class )
			->setConstructorArgs( [
				$this->createMock( ActorStore::class ),
				$database,
				24 * 60 * 60,
			] )
			->onlyMethods( [ 'createManualLogEntry' ] )
			->getMock();

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
			->with( Logger::ACTION_CHANGE_ACCESS )
			->willReturn( $logEntry );

		$logger->$logMethod( $performer );
	}
}
