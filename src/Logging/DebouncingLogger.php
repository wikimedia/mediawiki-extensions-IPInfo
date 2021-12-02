<?php

namespace MediaWiki\IPInfo\Logging;

use IDatabase;
use ManualLogEntry;
use MediaWiki\User\UserIdentity;
use Title;

/**
 * Only logs the performer performing an action if a duplicate log entry has not been inserted
 * within a given number seconds.
 */
class DebouncingLogger implements Logger {
	/**
	 * @var string
	 */
	private const LOG_TYPE = 'ipinfo';

	/**
	 * @var int
	 */
	private $delay;

	/**
	 * @var IDatabase
	 */
	private $dbw;

	/**
	 * @param int $delay The number of seconds after which a duplicate log entry can be inserted
	 * @param IDatabase $dbw
	 */
	public function __construct( int $delay, IDatabase $dbw ) {
		$this->delay = $delay;
		$this->dbw = $dbw;
	}

	/**
	 * @inheritDoc
	 */
	public function logViewAccordion( UserIdentity $performer, string $ip ): void {
		$this->log( $performer, $ip, Logger::ACTION_VIEW_ACCORDION );
	}

	/**
	 * @inheritDoc
	 */
	public function logViewPopup( UserIdentity $performer, string $ip ): void {
		$this->log( $performer, $ip, Logger::ACTION_VIEW_POPUP );
	}

	/**
	 * @param UserIdentity $performer
	 * @param string $ip
	 * @param string $action Either `Logger::ACTION_VIEW_ACCORDION` or `Logger::ACTION_VIEW_POPUP`
	 */
	private function log( UserIdentity $performer, string $ip, string $action ): void {
		$timestamp = (int)wfTimestampNow() - $this->delay;
		$shouldDebounce = $this->dbw->selectRowCount(
			'logging',
			'*',
			[
				'log_type' => self::LOG_TYPE,
				'log_action' => $action,
				'log_actor' => $performer->getId(),
				'log_namespace' => NS_USER,
				'log_title' => $ip,
				'log_timestamp > ' . $this->dbw->addQuotes( $this->dbw->timestamp( $timestamp ) ),
			]
		);

		if ( $shouldDebounce ) {
			return;
		}

		$logEntry = $this->createManualLogEntry( $action );
		$logEntry->setPerformer( $performer );
		$logEntry->setTarget( Title::makeTitle( NS_USER, $ip ) );

		$logEntry->insert( $this->dbw );
	}

	/**
	 * There is no `LogEntryFactory` (or `Logger::insert()` method) in MediaWiki Core to inject
	 * via the constructor so use this method to isolate the creation of `LogEntry` objects during
	 * testing.
	 *
	 * @private
	 *
	 * @param string $subtype
	 * @return ManualLogEntry
	 */
	protected function createManualLogEntry( string $subtype ): ManualLogEntry {
		return new ManualLogEntry( self::LOG_TYPE, $subtype );
	}
}
