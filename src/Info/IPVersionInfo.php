<?php

declare( strict_types=1 );

namespace MediaWiki\IPInfo\Info;

class IPVersionInfo {

	public function __construct(
		private readonly ?string $version,
	) {
	}

	public function getVersion(): ?string {
		return $this->version;
	}
}
