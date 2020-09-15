<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Info implements JsonSerializable {
	/** @var Coordinates|null */
	private $coordinates;

	/** @var ASN|null */
	private $asn;

	/** @var Location[] */
	private $location;

	/**
	 * @param Coordinates|null $coordinates
	 * @param ASN|null $asn
	 * @param Location[] $location
	 */
	public function __construct(
		?Coordinates $coordinates = null,
		?ASN $asn = null,
		array $location = []
	) {
		$this->coordinates = $coordinates;
		$this->asn = $asn;
		$this->location = $location;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() {
		$data = [
			'coordinates' => $this->coordinates,
			'asn' => $this->asn,
			'location' => $this->location,
		];

		return (object)array_filter( $data, function ( $value ) {
			return $value !== null && $value !== [];
		} );
	}
}
