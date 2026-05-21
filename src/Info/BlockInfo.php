<?php

declare( strict_types=1 );

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class BlockInfo implements JsonSerializable {

	public function __construct(
		private readonly int $numActiveBlocks = 0,
	) {
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
