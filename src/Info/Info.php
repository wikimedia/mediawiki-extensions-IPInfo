<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Info implements JsonSerializable {
	/** @var string Actor name */
	private $actor;

	/** @var Coordinates|null */
	private $coordinates;

	/** @var ASN|null */
	private $asn;

	/** @var Location[] */
	private $location;

	/**
	 * @param string $actor
	 * @param Coordinates|null $coordinates
	 * @param ASN|null $asn
	 * @param Location[] $location
	 */
	public function __construct(
		string $actor,
		?Coordinates $coordinates = null,
		?ASN $asn = null,
		array $location = []
	) {
		$this->actor = $actor;
		$this->coordinates = $coordinates;
		$this->asn = $asn;
		$this->location = $location;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() {
		return [
			'actor' => $this->actor,
			'coordinates' => $this->coordinates,
			'asn' => $this->asn,
			'location' => $this->location,
		];
	}
}
