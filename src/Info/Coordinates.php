<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Coordinates implements JsonSerializable {
	/** @var float */
	private $latitude;

	/** @var float */
	private $longitude;

	/**
	 * @param float $latitude
	 * @param float $longitude
	 */
	public function __construct(
		float $latitude,
		float $longitude
	) {
		$this->latitude = $latitude;
		$this->longitude = $longitude;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() {
		return [
			'latitude' => $this->latitude,
			'longitude' => $this->longitude,
		];
	}
}
