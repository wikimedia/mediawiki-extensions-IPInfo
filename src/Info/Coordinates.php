<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Coordinates implements JsonSerializable {
	public function __construct(
		private readonly float $latitude,
		private readonly float $longitude,
	) {
	}

	public function getLatitude(): float {
		return $this->latitude;
	}

	public function getLongitude(): float {
		return $this->longitude;
	}

	public function jsonSerialize(): array {
		return [
			'longitude' => $this->getLongitude(),
			'latitude' => $this->getLatitude(),
		];
	}
}
