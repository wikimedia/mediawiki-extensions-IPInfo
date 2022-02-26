<?php

namespace MediaWiki\IPInfo\Logging;

use ManualLogEntry;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentity;
use Title;
use Wikimedia\Assert\Assert;
use Wikimedia\Assert\ParameterAssertionException;
use Wikimedia\Rdbms\IDatabase;

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
	 * An ordered list of the access levels for viewing IP infomation, ordered from lowest to
	 * highest level.
	 *
	 * Should be kept up-to-date with DefaultPresenter::VIEWING_RIGHTS
	 *
	 * @var string[]
	 */
	private const ACCESS_LEVELS = [
		'ipinfo-view-basic',
		'ipinfo-view-full',
	];

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
	 * @var string
	 */
	public const LOG_TYPE = 'ipinfo';

	/**
	 * @var ActorStore
	 */
	private $actorStore;

	/**
	 * @var PermissionManager
	 */
	private $permissionManager;

	/**
	 * @var IDatabase
	 */
	private $dbw;

	/**
	 * @var int
	 */
	private $delay;

	/**
	 * @param ActorStore $actorStore
	 * @param PermissionManager $permissionManager
	 * @param IDatabase $dbw
	 * @param int $delay The number of seconds after which a duplicate log entry can be
	 *  created by `Logger::logViewInfobox` or `Logger::logViewPopup`
	 * @throws ParameterAssertionException if `$delay` is less than 1
	 */
	public function __construct(
		ActorStore $actorStore,
		PermissionManager $permissionManager,
		IDatabase $dbw,
		int $delay
	) {
		Assert::parameter( $delay > 0, 'delay', 'delay must be positive' );

		$this->actorStore = $actorStore;
		$this->permissionManager = $permissionManager;
		$this->dbw = $dbw;
		$this->delay = $delay;
	}

	/**
	 * Logs the user (the performer) viewing information about an IP via the infobox.
	 *
	 * @param UserIdentity $performer
	 * @param string $ip
	 */
	public function logViewInfobox( UserIdentity $performer, string $ip ): void {
		$level = $this->highestAccessLevel( $performer );
		if ( !$level ) {
			return;
		}
		$params = [ '4::level' => $level ];
		$this->debouncedLog( $performer, $ip, self::ACTION_VIEW_INFOBOX, $params );
	}

	/**
	 * Logs the user (the performer) viewing information about an IP via the popup.
	 *
	 * @param UserIdentity $performer
	 * @param string $ip
	 */
	public function logViewPopup( UserIdentity $performer, string $ip ): void {
		$level = $this->highestAccessLevel( $performer );
		if ( !$level ) {
			return;
		}
		$params = [ '4::level' => $level ];
		$this->debouncedLog( $performer, $ip, self::ACTION_VIEW_POPUP, $params );
	}

	/**
	 * Get the highest access level user has permissions for.
	 *
	 * @param UserIdentity $user
	 * @return ?string null if the user has no rights to see IP information
	 */
	private function highestAccessLevel( $user ) {
		$userPermissions = $this->permissionManager->getUserPermissions( $user );

		$highestLevel = null;
		foreach ( self::ACCESS_LEVELS as $level ) {
			if ( in_array( $level, $userPermissions ) ) {
				$highestLevel = $level;
			}
		}

		return $highestLevel;
	}

	/**
	 * @param UserIdentity $performer
	 * @param string $ip
	 * @param string $action Either `Logger::ACTION_VIEW_INFOBOX` or
	 *  `Logger::ACTION_VIEW_POPUP`
	 * @param array $params
	 */
	private function debouncedLog(
		UserIdentity $performer,
		string $ip,
		string $action,
		array $params
	): void {
		$timestamp = (int)wfTimestampNow() - $this->delay;

		$actorId = $this->actorStore->acquireActorId( $performer, $this->dbw );
		$shouldLog = $this->dbw->selectRowCount(
			'logging',
			'*',
			[
				'log_type' => self::LOG_TYPE,
				'log_action' => $action,
				'log_actor' => $actorId,
				'log_namespace' => NS_USER,
				'log_title' => $ip,
				'log_timestamp > ' . $this->dbw->addQuotes( $this->dbw->timestamp( $timestamp ) ),
				'log_params' . $this->dbw->buildLike(
					$this->dbw->anyString(),
					$params['4::level'],
					$this->dbw->anyString()
				),
			]
		) === 0;

		if ( $shouldLog ) {
			$this->log( $performer, $ip, $action, $params );
		}
	}

	/**
	 * @param UserIdentity $performer
	 * @param string $ip
	 * @param string $action
	 * @param array $params
	 */
	private function log(
		UserIdentity $performer,
		string $ip,
		string $action,
		array $params
	): void {
		$logEntry = $this->createManualLogEntry( $action );
		$logEntry->setPerformer( $performer );
		$logEntry->setTarget( Title::makeTitle( NS_USER, $ip ) );
		$logEntry->setParameters( $params );

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
