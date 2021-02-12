<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Asn implements JsonSerializable {
	/** @var int */
	private $asn;

	/**
	 * @param int $asn
	 */
	public function __construct( int $asn ) {
		$this->asn = $asn;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() {
		return $this->asn;
	}
}
