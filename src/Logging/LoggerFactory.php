<?php

namespace MediaWiki\IPInfo\Logging;

use IDatabase;
use MediaWiki\User\ActorStore;

class LoggerFactory {

	/**
	 * The default amount of time after which a duplicate log entry can be inserted. 24 hours (in
	 * seconds).
	 *
	 * @var int
	 */
	private const DEFAULT_DEBOUNCE_DELAY = 24 * 60 * 60;

	/** @var IDatabase */
	private $dbw;

	/** @var ActorStore */
	private $actorStore;

	/**
	 * @param ActorStore $actorStore
	 * @param IDatabase $dbw
	 */
	public function __construct(
		ActorStore $actorStore,
		IDatabase $dbw
	) {
		$this->actorStore = $actorStore;
		$this->dbw = $dbw;
	}

	/**
	 * @param int $delay
	 * @return Logger
	 */
	public function getLogger(
		int $delay = self::DEFAULT_DEBOUNCE_DELAY
	) {
		return new Logger(
			$this->actorStore,
			$this->dbw,
			$delay
		);
	}
}
