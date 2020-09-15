<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class ASN implements JsonSerializable {
	/** @var int */
	private $id;

	/**
	 * @param int $id
	 */
	public function __construct( int $id ) {
		$this->id = $id;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() {
		return [
			'id' => $this->id,
		];
	}
}
