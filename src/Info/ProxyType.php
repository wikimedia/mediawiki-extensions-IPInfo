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
	 * @param bool|null $isAnonymous
	 * @param bool|null $isAnonymousVpn
	 * @param bool|null $isPublicProxy
	 * @param bool|null $isResidentialProxy
	 * @param bool|null $isLegitimateProxy
	 * @param bool|null $isTorExitNode
	 */
	public function __construct(
		?bool $isAnonymous,
		?bool $isAnonymousVpn,
		?bool $isPublicProxy,
		?bool $isResidentialProxy,
		?bool $isLegitimateProxy,
		?bool $isTorExitNode
	) {
		$this->isAnonymous = $isAnonymous;
		$this->isAnonymousVpn = $isAnonymousVpn;
		$this->isPublicProxy = $isPublicProxy;
		$this->isResidentialProxy = $isResidentialProxy;
		$this->isLegitimateProxy = $isLegitimateProxy;
		$this->isTorExitNode = $isTorExitNode;
	}

	public function isAnonymous(): ?bool {
		return $this->isAnonymous;
	}

	public function isAnonymousVpn(): ?bool {
		return $this->isAnonymousVpn;
	}

	public function isPublicProxy(): ?bool {
		return $this->isPublicProxy;
	}

	public function isResidentialProxy(): ?bool {
		return $this->isResidentialProxy;
	}

	public function isLegitimateProxy(): ?bool {
		return $this->isLegitimateProxy;
	}

	public function isTorExitNode(): bool {
		return $this->isTorExitNode;
	}
}
