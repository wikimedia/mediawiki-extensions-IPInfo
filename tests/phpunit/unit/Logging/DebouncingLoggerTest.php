<?php

namespace MediaWiki\IPInfo\Test\Unit\Logging;

use Generator;
use IDatabase;
use ManualLogEntry;
use MediaWiki\IPInfo\Logging\DebouncingLogger;
use MediaWiki\IPInfo\Logging\Logger;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Title;

/**
 * @coversDefaultClass \MediaWiki\IPInfo\Logging\DebouncingLogger
 */
class DebouncingLoggerTest extends MediaWikiUnitTestCase {
	public function provideLogViewAccordion(): Generator {
		yield [ 'isDebounced' => true ];
		yield [ 'isDebounced' => false ];
	}

	/**
	 * @dataProvider provideLogViewAccordion
	 * @covers ::logViewAccordion
	 * @covers ::log
	 */
	public function testLogViewAccordion( bool $isDebounced ): void {
		$performer = new UserIdentityValue( 1, 'Foo' );
		$target = '127.0.0.1';

		$expectedTarget = Title::makeTitle( NS_USER, $target );

		$database = $this->createMock( IDatabase::class );

		// We don't need to stub IDatabase::timestamp() since it is wrapped in
		// a call to IDatabase::addQuotes().
		$database->method( 'addQuotes' )
			->willReturn( 42 );

		$database->expects( $this->once() )
			->method( 'selectRowCount' )
			->with(
				'logging',
				'*',
				[
					'log_type' => 'ipinfo',
					'log_action' => Logger::ACTION_VIEW_ACCORDION,
					'log_actor' => $performer->getId(),
					'log_namespace' => NS_USER,
					'log_title' => $target,
					'log_timestamp > 42',
				]
			)
			->willReturn( (int)$isDebounced );

		$logger = $this->getMockBuilder( DebouncingLogger::class )
			->setConstructorArgs( [ 1, $database ] )
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
				->method( 'insert' )
				->with( $database );

			$logger->expects( $this->once() )
				->method( 'createManualLogEntry' )
				->with( Logger::ACTION_VIEW_ACCORDION )
				->willReturn( $logEntry );
		}

		$logger->logViewAccordion( $performer, $target );
	}
}
