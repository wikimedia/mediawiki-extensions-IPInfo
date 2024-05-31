<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Info implements JsonSerializable {
	/** @var Coordinates|null */
	private $coordinates;

	/** @var int|null */
	private $asn;

	/** @var string|null */
	private $organization;

	/** @var array|null */
	private $countryNames;

	/** @var array|null */
	private $location;

	/** @var string|null */
	private $isp;

	/** @var string|null */
	private $connectionType;

	/** @var string|null */
	private $userType;

	/** @var ProxyType|null */
	private $proxyType;

	/**
	 * @param Coordinates|null $coordinates
	 * @param int|null $asn
	 * @param string|null $organization
	 * @param array|null $countryNames
	 * @param Location[]|null $location
	 * @param string|null $isp
	 * @param string|null $connectionType
	 * @param string|null $userType
	 * @param ProxyType|null $proxyType
	 */
	public function __construct(
		?Coordinates $coordinates = null,
		?int $asn = null,
		?string $organization = null,
		?array $countryNames = null,
		?array $location = null,
		?string $isp = null,
		?string $connectionType = null,
		?string $userType = null,
		?ProxyType $proxyType = null
	) {
		$this->coordinates = $coordinates;
		$this->asn = $asn;
		$this->organization = $organization;
		$this->countryNames = $countryNames;
		$this->location = $location;
		$this->isp = $isp;
		$this->connectionType = $connectionType;
		$this->userType = $userType;
		$this->proxyType = $proxyType;
	}

	/**
	 * @return Coordinates|null
	 */
	public function getCoordinates(): ?Coordinates {
		return $this->coordinates;
	}

	/**
	 * @return int|null
	 */
	public function getAsn(): ?int {
		return $this->asn;
	}

	/**
	 * @return string|null
	 */
	public function getOrganization(): ?string {
		return $this->organization;
	}

	/**
	 * @return array|null
	 */
	public function getCountryNames(): ?array {
		return $this->countryNames;
	}

	/**
	 * @return Location[]|null
	 */
	public function getLocation(): ?array {
		return $this->location;
	}

	/**
	 * @return string|null
	 */
	public function getIsp(): ?string {
		return $this->isp;
	}

	/**
	 * @return string|null
	 */
	public function getConnectionType(): ?string {
		return $this->connectionType;
	}

	/**
	 * @return string|null
	 */
	public function getUserType(): ?string {
		return $this->userType;
	}

	/**
	 * @return ProxyType|null
	 */
	public function getProxyType(): ?ProxyType {
		return $this->proxyType;
	}

	public function jsonSerialize(): array {
		return [
			'coordinates' => $this->getCoordinates(),
			'asn' => $this->getAsn(),
			'organization' => $this->getOrganization(),
			'countryNames' => $this->getCountryNames(),
			'location' => $this->getLocation(),
			'isp' => $this->getIsp(),
			'connectionType' => $this->getConnectionType(),
			'userType' => $this->getUserType(),
			'proxyType' => $this->getProxyType(),
		];
	}
}
