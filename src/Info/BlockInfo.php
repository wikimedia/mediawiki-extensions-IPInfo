<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class BlockInfo implements JsonSerializable {

	private int $numActiveBlocks;

	public function __construct( int $numActiveBlocks = 0 ) {
		$this->numActiveBlocks = $numActiveBlocks;
	}

	public function getNumActiveBlocks(): int {
		return $this->numActiveBlocks;
	}

	public function jsonSerialize(): array {
		return [
			'numActiveBlocks' => $this->getNumActiveBlocks(),
		];
	}
}
