<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Organization implements JsonSerializable {
	/** @var string */
	private $organization;

	/**
	 * @param string $organization
	 */
	public function __construct( string $organization ) {
		$this->organization = $organization;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() {
		return $this->organization;
	}
}
