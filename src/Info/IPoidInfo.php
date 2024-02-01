<?php

namespace MediaWiki\IPInfo\Info;

class IPoidInfo {

	/** @var string[]|null */
	private $behaviors;

	/** @var string[]|null */
	private $risks;

	/** @var string[]|null */
	private $connectionTypes;

	/** @var string[]|null */
	private $tunnelOperators;

	/** @var string[]|null */
	private $proxies;

	/** @var int|null */
	private $numUsersOnThisIP;

	/**
	 * @param string[]|null $behaviors
	 * @param string[]|null $risks
	 * @param string[]|null $connectionTypes
	 * @param string[]|null $tunnelOperators
	 * @param string[]|null $proxies
	 * @param int|null $numUsersOnThisIP
	 */
	public function __construct(
		?array $behaviors = null,
		?array $risks = null,
		?array $connectionTypes = null,
		?array $tunnelOperators = null,
		?array $proxies = null,
		int $numUsersOnThisIP = null
	) {
		$this->behaviors = $behaviors;
		$this->risks = $risks;
		$this->connectionTypes = $connectionTypes;
		$this->tunnelOperators = $tunnelOperators;
		$this->proxies = $proxies;
		$this->numUsersOnThisIP = $numUsersOnThisIP;
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
}
