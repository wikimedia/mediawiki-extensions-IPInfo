<?php

namespace MediaWiki\IPInfo\Logging;

use IDatabase;

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

	/**
	 * @param IDatabase $dbw
	 */
	public function __construct( IDatabase $dbw ) {
		$this->dbw = $dbw;
	}

	/**
	 * @param int $delay
	 * @return Logger
	 */
	public function getLogger( int $delay = self::DEFAULT_DEBOUNCE_DELAY ) {
		return new Logger( $this->dbw, $delay );
	}
}
