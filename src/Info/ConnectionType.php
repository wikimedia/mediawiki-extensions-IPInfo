<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class ConnectionType implements JsonSerializable {
	/** @var string */
	private $connectionType;

	/**
	 * @param string $connectionType
	 */
	public function __construct( string $connectionType ) {
		$this->connectionType = $connectionType;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() {
		return $this->connectionType;
	}
}
