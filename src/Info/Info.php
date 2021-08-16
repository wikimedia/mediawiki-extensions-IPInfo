<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Info implements JsonSerializable {
	/** @var Coordinates|null */
	private $coordinates;

	/** @var Asn|null */
	private $asn;

	/** @var Organization|null */
	private $organization;

	/** @var Location[] */
	private $location;

	/** @var Isp|null */
	private $isp;

	/** @var ConnectionType|null */
	private $connectionType;

	/** @var ProxyType|null */
	private $proxyType;

	/**
	 * @param Coordinates|null $coordinates
	 * @param Asn|null $asn
	 * @param Organization|null $organization
	 * @param Location[] $location
	 * @param Isp|null $isp
	 * @param ConnectionType|null $connectionType
	 * @param ProxyType|null $proxyType
	 */
	public function __construct(
		?Coordinates $coordinates = null,
		?Asn $asn = null,
		?Organization $organization = null,
		array $location = [],
		?Isp $isp = null,
		?ConnectionType $connectionType = null,
		?ProxyType $proxyType = null
	) {
		$this->coordinates = $coordinates;
		$this->asn = $asn;
		$this->organization = $organization;
		$this->location = $location;
		$this->isp = $isp;
		$this->connectionType = $connectionType;
		$this->proxyType = $proxyType;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() {
		return [
			'coordinates' => $this->coordinates,
			'asn' => $this->asn,
			'organization' => $this->organization,
			'location' => $this->location,
			'isp' => $this->isp,
			'connectionType' => $this->connectionType,
			'proxyType' => $this->proxyType,
		];
	}
}
