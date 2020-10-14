<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Info implements JsonSerializable {
	/** @var string */
	private $source;

	/** @var Coordinates|null */
	private $coordinates;

	/** @var ASN|null */
	private $asn;

	/** @var Location[] */
	private $location;

	/**
	 * @param string $source Message key for the name of the data source
	 * @param Coordinates|null $coordinates
	 * @param ASN|null $asn
	 * @param Location[] $location
	 */
	public function __construct(
		string $source,
		?Coordinates $coordinates = null,
		?ASN $asn = null,
		array $location = []
	) {
		$this->source = $source;
		$this->coordinates = $coordinates;
		$this->asn = $asn;
		$this->location = $location;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() {
		return [
			'source' => $this->source,
			'coordinates' => $this->coordinates,
			'asn' => $this->asn,
			'location' => $this->location,
		];
	}
}
