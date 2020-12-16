<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class ProxyType implements JsonSerializable {
	/** @var bool */
	private $isAnonymous;

	/** @var bool */
	private $isAnonymousVpn;

	/** @var bool */
	private $isPublicProxy;

	/** @var bool */
	private $isResidentialProxy;

	/** @var bool */
	private $isLegitimateProxy;

	/** @var bool */
	private $isTorExitNode;

	/**
	 * @param bool $isAnonymous
	 * @param bool $isAnonymousVpn
	 * @param bool $isPublicProxy
	 * @param bool $isResidentialProxy
	 * @param bool $isLegitimateProxy
	 * @param bool $isTorExitNode
	 */
	public function __construct(
		bool $isAnonymous,
		bool $isAnonymousVpn,
		bool $isPublicProxy,
		bool $isResidentialProxy,
		bool $isLegitimateProxy,
		bool $isTorExitNode
	) {
		$this->isAnonymous = $isAnonymous;
		$this->isAnonymousVpn = $isAnonymousVpn;
		$this->isPublicProxy = $isPublicProxy;
		$this->isResidentialProxy = $isResidentialProxy;
		$this->isLegitimateProxy = $isLegitimateProxy;
		$this->isTorExitNode = $isTorExitNode;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() {
		return [
			'isAnonymous' => $this->isAnonymous,
			'isAnonymousVpn' => $this->isAnonymousVpn,
			'isPublicProxy' => $this->isPublicProxy,
			'isResidentialProxy' => $this->isResidentialProxy,
			'isLegitimateProxy' => $this->isLegitimateProxy,
			'isTorExitNode' => $this->isTorExitNode,
		];
	}
}
