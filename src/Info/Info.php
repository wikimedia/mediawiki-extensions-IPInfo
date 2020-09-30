<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Info implements JsonSerializable {
	/** @var string */
	private $subject;

	/** @var Coordinates|null */
	private $coordinates;

	/** @var ASN|null */
	private $asn;

	/** @var Location[] */
	private $location;

	/**
	 * @param string $subject
	 * @param Coordinates|null $coordinates
	 * @param ASN|null $asn
	 * @param Location[] $location
	 */
	public function __construct(
		string $subject,
		?Coordinates $coordinates = null,
		?ASN $asn = null,
		array $location = []
	) {
		$this->subject = $subject;
		$this->coordinates = $coordinates;
		$this->asn = $asn;
		$this->location = $location;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() {
		return [
			'subject' => $this->subject,
			'coordinates' => $this->coordinates,
			'asn' => $this->asn,
			'location' => $this->location,
		];
	}
}
