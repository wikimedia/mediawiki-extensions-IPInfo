<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Isp implements JsonSerializable {
	/** @var string */
	private $isp;

	/**
	 * @param string $isp
	 */
	public function __construct( string $isp ) {
		$this->isp = $isp;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() {
		return $this->isp;
	}
}
