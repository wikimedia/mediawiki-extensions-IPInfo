<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Location implements JsonSerializable {
	/** @var int|null */
	private $id;

	/** @var string|null */
	private $label;

	/**
	 * @param int|null $id
	 * @param string|null $label
	 */
	public function __construct(
		?int $id = null,
		?string $label = null
	) {
		$this->id = $id;
		$this->label = $label;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() {
		$data = [
			'id' => $this->id,
			'label' => $this->label,
		];

		return (object)array_filter( $data, function ( $value ) {
			return $value !== null;
		} );
	}
}
