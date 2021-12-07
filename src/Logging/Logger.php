<?php

namespace MediaWiki\IPInfo\Logging;

use IDatabase;
use ManualLogEntry;
use MediaWiki\User\UserIdentity;
use Title;
use Wikimedia\Assert\Assert;
use Wikimedia\Assert\ParameterAssertionException;

/**
 * Defines the API for the component responsible for logging the following interactions:
 *
 * 1. A user views information about an IP via the infobox
 * 2. A user views information about an IP via the popup
 * 3. A user enables IP Info (via Special:Preferences)
 * 4. A user disables IP Info
 * 5. A user has their access revoked
 * 6. A user has their access (re-)granted
 *
 * 1 and 2 are debounced. By default, if the same user views information about the same IP via the
 * same treatment within 24 hours, then only one such action should be logged.
 *
 * 3-6 will be implemented as part of T292842.
 *
 * All the above interactions will be logged to the `logging` table with a log type `ipinfo`.
 */
class Logger {

	/**
	 * The default amount of time after which a duplicate log entry can be inserted. 24 hours (in
	 * seconds).
	 *
	 * @var int
	 */
	private const DEFAULT_DEBOUNCE_DELAY = 24 * 60 * 60;

	/**
	 * Represents a user (the performer) viewing information about an IP via the infobox.
	 *
	 * @var string
	 */
	public const ACTION_VIEW_ACCORDION = 'view_accordion';

	/**
	 * Represents a user (the performer) viewing information about an IP via the popup.
	 *
	 * @var string
	 */
	public const ACTION_VIEW_POPUP = 'view_popup';

	/**
	 * @var string
	 */
	public const LOG_TYPE = 'ipinfo';

	/**
	 * @var IDatabase
	 */
	private $dbw;

	/**
	 * @var int
	 */
	private $delay;

	/**
	 * @param IDatabase $dbw
	 * @param int $delay The number of seconds after which a duplicate log entry can be
	 *  created by `Logger::logViewAccordion` or `Logger::logViewPopup`
	 * @throws ParameterAssertionException if `$delay` is less than 1
	 */
	public function __construct( IDatabase $dbw, int $delay = self::DEFAULT_DEBOUNCE_DELAY ) {
		Assert::parameter( $delay > 0, 'delay', 'delay must be positive' );

		$this->dbw = $dbw;
		$this->delay = $delay;
	}

	/**
	 * Logs the user (the performer) viewing information about an IP via the infobox.
	 *
	 * @param UserIdentity $performer
	 * @param string $ip
	 */
	public function logViewAccordion( UserIdentity $performer, string $ip ): void {
		$this->debouncedLog( $performer, $ip, self::ACTION_VIEW_ACCORDION );
	}

	/**
	 * Logs the user (the performer) viewing information about an IP via the popup.
	 *
	 * @param UserIdentity $performer
	 * @param string $ip
	 */
	public function logViewPopup( UserIdentity $performer, string $ip ): void {
		$this->debouncedLog( $performer, $ip, self::ACTION_VIEW_POPUP );
	}

	/**
	 * @param UserIdentity $performer
	 * @param string $ip
	 * @param string $action Either `Logger::ACTION_VIEW_ACCORDION` or
	 *  `Logger::ACTION_VIEW_POPUP`
	 */
	private function debouncedLog( UserIdentity $performer, string $ip, string $action ): void {
		$timestamp = (int)wfTimestampNow() - $this->delay;
		$shouldLog = $this->dbw->selectRowCount(
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
		) === 0;

		if ( $shouldLog ) {
			$this->log( $performer, $ip, $action );
		}
	}

	/**
	 * @param UserIdentity $performer
	 * @param string $ip
	 * @param string $action
	 */
	private function log( UserIdentity $performer, string $ip, string $action ): void {
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
