<?php

namespace MediaWiki\IPInfo\Logging;

use MediaWiki\User\ActorStore;
use Wikimedia\Rdbms\IConnectionProvider;

class LoggerFactory {

	/**
	 * The default amount of time after which a duplicate log entry can be inserted. 24 hours (in
	 * seconds).
	 *
	 * @var int
	 */
	private const DEFAULT_DEBOUNCE_DELAY = 24 * 60 * 60;

	private ActorStore $actorStore;

	private IConnectionProvider $dbProvider;

	public function __construct(
		ActorStore $actorStore,
		IConnectionProvider $dbProvider
	) {
		$this->actorStore = $actorStore;
		$this->dbProvider = $dbProvider;
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
			$this->dbProvider->getPrimaryDatabase(),
			$delay
		);
	}
}
