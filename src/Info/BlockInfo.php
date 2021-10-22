<?php

namespace MediaWiki\IPInfo\Info;

class BlockInfo {

	/** @var int */
	private $numActiveBlocks;

	/**
	 * @param int $numActiveBlocks
	 */
	public function __construct( int $numActiveBlocks = 0 ) {
		$this->numActiveBlocks = $numActiveBlocks;
	}

	/**
	 * @return int
	 */
	public function getNumActiveBlocks(): int {
		return $this->numActiveBlocks;
	}
}
