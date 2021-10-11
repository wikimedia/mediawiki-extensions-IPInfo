<?php

namespace MediaWiki\IPInfo\Info;

class ContributionInfo {

	/** @var int */
	private $numLocalEdits;

	/** @var int */
	private $numRecentEdits;

	/**
	 * @param int $numLocalEdits
	 * @param int $numRecentEdits
	 */
	public function __construct(
		int $numLocalEdits = 0,
		int $numRecentEdits = 0
	) {
		$this->numLocalEdits = $numLocalEdits;
		$this->numRecentEdits = $numRecentEdits;
	}

	/**
	 * @return int
	 */
	public function getnumLocalEdits(): int {
		return $this->numLocalEdits;
	}

	/**
	 * @return int
	 */
	public function getNumRecentEdits(): int {
		return $this->numRecentEdits;
	}
}
