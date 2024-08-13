<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class ProxyType implements JsonSerializable {
	/** @var bool|null */
	private $isAnonymousVpn;

	/** @var bool|null */
	private $isPublicProxy;

	/** @var bool|null */
	private $isResidentialProxy;

	/** @var bool|null */
	private $isLegitimateProxy;

	/** @var bool|null */
	private $isTorExitNode;

	/** @var bool|null */
	private $isHostingProvider;

	public function __construct(
		?bool $isAnonymousVpn,
		?bool $isPublicProxy,
		?bool $isResidentialProxy,
		?bool $isLegitimateProxy,
		?bool $isTorExitNode,
		?bool $isHostingProvider
	) {
		$this->isAnonymousVpn = $isAnonymousVpn;
		$this->isPublicProxy = $isPublicProxy;
		$this->isResidentialProxy = $isResidentialProxy;
		$this->isLegitimateProxy = $isLegitimateProxy;
		$this->isTorExitNode = $isTorExitNode;
		$this->isHostingProvider = $isHostingProvider;
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

	public function isTorExitNode(): ?bool {
		return $this->isTorExitNode;
	}

	public function isHostingProvider(): ?bool {
		return $this->isHostingProvider;
	}

	public function jsonSerialize(): array {
		return [
			'isAnonymousVpn' => $this->isAnonymousVpn(),
			'isResidentialProxy' => $this->isResidentialProxy(),
			'isLegitimateProxy' => $this->isLegitimateProxy(),
			'isTorExitNode' => $this->isTorExitNode(),
			'isHostingProvider' => $this->isHostingProvider(),
		];
	}
}
