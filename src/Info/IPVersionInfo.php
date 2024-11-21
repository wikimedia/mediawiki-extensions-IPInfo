<?php
namespace MediaWiki\IPInfo\Info;

class IPVersionInfo {

	private ?string $version;

	public function __construct( ?string $version ) {
		$this->version = $version;
	}

	public function getVersion(): ?string {
		return $this->version;
	}
}
