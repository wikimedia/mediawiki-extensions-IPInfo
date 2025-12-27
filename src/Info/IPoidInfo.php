<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class IPoidInfo implements JsonSerializable {

	/**
	 * @param string[]|null $behaviors
	 * @param string[]|null $risks
	 * @param string[]|null $connectionTypes
	 * @param string[]|null $tunnelOperators
	 * @param string[]|null $proxies
	 * @param int|null $numUsersOnThisIP
	 */
	public function __construct(
		private readonly ?array $behaviors = null,
		private readonly ?array $risks = null,
		private readonly ?array $connectionTypes = null,
		private readonly ?array $tunnelOperators = null,
		private readonly ?array $proxies = null,
		private readonly ?int $numUsersOnThisIP = null,
	) {
	}

	/**
	 * @return string[]|null
	 */
	public function getBehaviors(): ?array {
		return $this->behaviors;
	}

	/**
	 * @return string[]|null
	 */
	public function getRisks(): ?array {
		return $this->risks;
	}

	/**
	 * @return string[]|null
	 */
	public function getConnectionTypes(): ?array {
		return $this->connectionTypes;
	}

	/**
	 * @return string[]|null
	 */
	public function getTunnelOperators(): ?array {
		return $this->tunnelOperators;
	}

	/**
	 * @return string[]|null
	 */
	public function getProxies(): ?array {
		return $this->proxies;
	}

	public function getNumUsersOnThisIP(): ?int {
		return $this->numUsersOnThisIP;
	}

	public function jsonSerialize(): array {
		return [
			'behaviors' => $this->getBehaviors(),
			'risks' => $this->getRisks(),
			'connectionTypes' => $this->getConnectionTypes(),
			'tunnelOperators' => $this->getTunnelOperators(),
			'proxies' => $this->getProxies(),
			'numUsersOnThisIP' => $this->getNumUsersOnThisIP(),
		];
	}
}
