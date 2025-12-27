<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Info implements JsonSerializable {
	/**
	 * @param Coordinates|null $coordinates
	 * @param int|null $asn
	 * @param string|null $organization
	 * @param array<string,string>|null $countryNames
	 * @param Location[]|null $location
	 * @param string|null $connectionType
	 * @param string|null $userType
	 * @param ProxyType|null $proxyType
	 */
	public function __construct(
		private readonly ?Coordinates $coordinates = null,
		private readonly ?int $asn = null,
		private readonly ?string $organization = null,
		private readonly ?array $countryNames = null,
		private readonly ?array $location = null,
		private readonly ?string $connectionType = null,
		private readonly ?string $userType = null,
		private readonly ?ProxyType $proxyType = null,
	) {
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
	 * @return array<string,string>|null
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
			'connectionType' => $this->getConnectionType(),
			'userType' => $this->getUserType(),
			'proxyType' => $this->getProxyType(),
		];
	}
}
