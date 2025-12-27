<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class ProxyType implements JsonSerializable {
	public function __construct(
		private readonly ?bool $isAnonymousVpn,
		private readonly ?bool $isPublicProxy,
		private readonly ?bool $isResidentialProxy,
		private readonly ?bool $isLegitimateProxy,
		private readonly ?bool $isTorExitNode,
		private readonly ?bool $isHostingProvider,
	) {
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
