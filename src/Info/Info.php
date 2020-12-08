<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Info implements JsonSerializable {
	/** @var string */
	private $source;

	/** @var Coordinates|null */
	private $coordinates;

	/** @var Asn|null */
	private $asn;

	/** @var Location[] */
	private $location;

	/** @var Isp|null */
	private $isp;

	/**
	 * @param string $source Message key for the name of the data source
	 * @param Coordinates|null $coordinates
	 * @param Asn|null $asn
	 * @param Location[] $location
	 * @param Isp|null $isp
	 */
	public function __construct(
		string $source,
		?Coordinates $coordinates = null,
		?Asn $asn = null,
		array $location = [],
		?Isp $isp = null
	) {
		$this->source = $source;
		$this->coordinates = $coordinates;
		$this->asn = $asn;
		$this->location = $location;
		$this->isp = $isp;
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
			'isp' => $this->isp,
		];
	}
}
