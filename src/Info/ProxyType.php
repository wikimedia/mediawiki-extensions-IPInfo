<?php

namespace MediaWiki\IPInfo\Info;

class ProxyType {
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

	public function isAnonymous(): bool {
		return $this->isAnonymous;
	}

	public function isAnonymousVpn(): bool {
		return $this->isAnonymousVpn;
	}

	public function isPublicProxy(): bool {
		return $this->isPublicProxy;
	}

	public function isResidentialProxy(): bool {
		return $this->isResidentialProxy;
	}

	public function isLegitimateProxy(): bool {
		return $this->isLegitimateProxy;
	}

	public function isTorExitNode(): bool {
		return $this->isTorExitNode;
	}
}
