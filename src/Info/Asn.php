<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Asn implements JsonSerializable {
	/** @var int */
	private $id;

	/** @var string */
	private $label;

	/**
	 * @param int $id
	 * @param string $label
	 */
	public function __construct( int $id, string $label ) {
		$this->id = $id;
		$this->label = $label;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'label' => $this->label,
		];
	}
}
