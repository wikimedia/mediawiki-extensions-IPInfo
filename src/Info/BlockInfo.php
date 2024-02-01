<?php

namespace MediaWiki\IPInfo\Info;

class BlockInfo {

	private int $numActiveBlocks;

	public function __construct( int $numActiveBlocks = 0 ) {
		$this->numActiveBlocks = $numActiveBlocks;
	}

	public function getNumActiveBlocks(): int {
		return $this->numActiveBlocks;
	}
}
