<?php

namespace MediaWiki\IPInfo\Logging;

use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Title\Title;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentity;
use Wikimedia\Assert\Assert;
use Wikimedia\Assert\ParameterAssertionException;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;

/**
 * Defines the API for the component responsible for logging the following interactions:
 *
 * 1. A user views information about a target user via the infobox
 * 2. A user views information about a target user via the popup
 * 3. A user enables IP Info (via Special:Preferences)
 * 4. A user disables IP Info
 *
 * 1 and 2 are debounced. By default, if the same user views information about the same target via the
 * same treatment within 24 hours, then only one such action should be logged.
 *
 * All the above interactions will be logged to the `logging` table with a log type `ipinfo`.
 */
class Logger {
	/**
	 * Represents a user (the performer) viewing information about an IP via the infobox.
	 *
	 * @var string
	 */
	public const ACTION_VIEW_INFOBOX = 'view_infobox';

	/**
	 * Represents a user (the performer) viewing information about an IP via the popup.
	 *
	 * @var string
	 */
	public const ACTION_VIEW_POPUP = 'view_popup';

	/**
	 * Represents a user enabling or disabling their own access to IPInfo
	 *
	 * @var string
	 */
	public const ACTION_CHANGE_ACCESS = 'change_access';

	/**
	 * @var string
	 */
	public const ACTION_ACCESS_ENABLED = 'enable';

	/**
	 * @var string
	 */
	public const ACTION_ACCESS_DISABLED = 'disable';

	/**
	 * @var string
	 */
	public const ACTION_GLOBAL_ACCESS_ENABLED = 'enable-globally';

	/**
	 * @var string
	 */
	public const ACTION_GLOBAL_ACCESS_DISABLED = 'disable-globally';

	/**
	 * @var string
	 */
	public const LOG_TYPE = 'ipinfo';

	/**
	 * @param ActorStore $actorStore
	 * @param IDatabase $dbw
	 * @param int $delay The number of seconds after which a duplicate log entry can be
	 *  created by `Logger::logViewInfobox` or `Logger::logViewPopup`
	 * @throws ParameterAssertionException if `$delay` is less than 1
	 */
	public function __construct(
		private readonly ActorStore $actorStore,
		private readonly IDatabase $dbw,
		private readonly int $delay,
	) {
		Assert::parameter( $delay > 0, 'delay', 'delay must be positive' );
	}

	/**
	 * Logs the user (the performer) viewing information about a target user via the infobox.
	 *
	 * @param UserIdentity $performer
	 * @param string $targetName
	 * @param int $timestamp
	 * @param string|null $level
	 */
	public function logViewInfobox(
		UserIdentity $performer,
		string $targetName,
		int $timestamp,
		?string $level
	): void {
		if ( !$level ) {
			return;
		}
		$params = [ '4::level' => $level ];
		$this->debouncedLog( $performer, $targetName, self::ACTION_VIEW_INFOBOX, $timestamp, $params );
	}

	/**
	 * Logs the user (the performer) viewing information about a target user via the popup.
	 *
	 * @param UserIdentity $performer
	 * @param string $targetName
	 * @param int $timestamp
	 * @param string|null $level
	 */
	public function logViewPopup( UserIdentity $performer, string $targetName, int $timestamp, ?string $level ): void {
		if ( !$level ) {
			return;
		}
		$params = [ '4::level' => $level ];
		$this->debouncedLog( $performer, $targetName, self::ACTION_VIEW_POPUP, $timestamp, $params );
	}

	/**
	 * Log when the user enables their own access
	 *
	 * @param UserIdentity $performer
	 */
	public function logAccessEnabled( UserIdentity $performer ): void {
		$params = [
			'4::changeType' => self::ACTION_ACCESS_ENABLED,
		];
		$this->log( $performer, $performer->getName(), self::ACTION_CHANGE_ACCESS, $params );
	}

	/**
	 * Log when the user disables their own access
	 *
	 * @param UserIdentity $performer
	 */
	public function logAccessDisabled( UserIdentity $performer ): void {
		$params = [
			'4::changeType' => self::ACTION_ACCESS_DISABLED,
		];
		$this->log( $performer, $performer->getName(), self::ACTION_CHANGE_ACCESS, $params );
	}

	/**
	 * Log when the user enables their own access globally.
	 *
	 * @param UserIdentity $performer
	 */
	public function logGlobalAccessEnabled( UserIdentity $performer ): void {
		$params = [
			'4::changeType' => self::ACTION_GLOBAL_ACCESS_ENABLED,
		];
		$this->log( $performer, $performer->getName(), self::ACTION_CHANGE_ACCESS, $params );
	}

	/**
	 * Log when the user disables their own access globally.
	 *
	 * @param UserIdentity $performer
	 */
	public function logGlobalAccessDisabled( UserIdentity $performer ): void {
		$params = [
			'4::changeType' => self::ACTION_GLOBAL_ACCESS_DISABLED,
		];
		$this->log( $performer, $performer->getName(), self::ACTION_CHANGE_ACCESS, $params );
	}

	/**
	 * @param UserIdentity $performer
	 * @param string $targetName
	 * @param string $action Either `Logger::ACTION_VIEW_INFOBOX` or
	 *  `Logger::ACTION_VIEW_POPUP`
	 * @param int $timestamp
	 * @param array $params
	 */
	private function debouncedLog(
		UserIdentity $performer,
		string $targetName,
		string $action,
		int $timestamp,
		array $params
	): void {
		$timestampMinusDelay = $timestamp - $this->delay;

		// Normalize the target name for display in case it is an anonymous IP address.
		// This is a no-op if the target is not an IP address.
		$targetName = IPUtils::sanitizeIP( $targetName );
		$actorId = $this->actorStore->findActorId( $performer, $this->dbw );
		if ( !$actorId ) {
			$this->log( $performer, $targetName, $action, $params );
			return;
		}

		$logLine = $this->dbw->newSelectQueryBuilder()
			->select( '*' )
			->from( 'logging' )
			->where( [
				'log_type' => self::LOG_TYPE,
				'log_action' => $action,
				'log_actor' => $actorId,
				'log_namespace' => NS_USER,
				'log_title' => $targetName,
				$this->dbw->expr( 'log_timestamp', '>', $this->dbw->timestamp( $timestampMinusDelay ) ),
				$this->dbw->expr( 'log_params', IExpression::LIKE, new LikeValue(
					$this->dbw->anyString(),
					$params['4::level'],
					$this->dbw->anyString()
				) ),
			] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$logLine ) {
			$this->log( $performer, $targetName, $action, $params, $timestamp );
		}
	}

	private function log(
		UserIdentity $performer,
		string $targetName,
		string $action,
		array $params,
		?int $timestamp = null
	): void {
		$logEntry = $this->createManualLogEntry( $action );
		$logEntry->setPerformer( $performer );
		$logEntry->setTarget( Title::makeTitle( NS_USER, $targetName ) );
		$logEntry->setParameters( $params );

		if ( $timestamp ) {
			$logEntry->setTimestamp( wfTimestamp( TS_MW, $timestamp ) );
		}

		$logEntry->insert( $this->dbw );
	}

	/**
	 * There is no `LogEntryFactory` (or `Logger::insert()` method) in MediaWiki Core to inject
	 * via the constructor, so use this method to isolate the creation of `LogEntry` objects during
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
