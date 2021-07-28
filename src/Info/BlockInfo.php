<?php

namespace MediaWiki\IPInfo\Info;

class BlockInfo {

	/** @var int */
	private $numActiveBlocks;

	/** @var int */
	private $numPastBlocks;

	/**
	 * @param int $numActiveBlocks
	 * @param int $numPastBlocks
	 */
	public function __construct(
		int $numActiveBlocks = 0,
		int $numPastBlocks = 0
	) {
		$this->numActiveBlocks = $numActiveBlocks;
		$this->numPastBlocks = $numPastBlocks;
	}

	/**
	 * @return int
	 */
	public function getNumActiveBlocks(): int {
		return $this->numActiveBlocks;
	}

	/**
	 * @return int
	 */
	public function getNumPastBlocks(): int {
		return $this->numPastBlocks;
	}
}
