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

	public function __construct(
		private readonly ActorStore $actorStore,
		private readonly IConnectionProvider $dbProvider,
	) {
	}

	public function getLogger(
		int $delay = self::DEFAULT_DEBOUNCE_DELAY
	): Logger {
		return new Logger(
			$this->actorStore,
			$this->dbProvider->getPrimaryDatabase(),
			$delay
		);
	}
}
