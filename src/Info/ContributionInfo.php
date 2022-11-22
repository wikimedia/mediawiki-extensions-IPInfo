<?php

namespace MediaWiki\IPInfo\Info;

class ContributionInfo {

	/** @var int */
	private $numLocalEdits;

	/** @var int */
	private $numRecentEdits;

	/** @var int */
	private $numDeletedEdits;

	/**
	 * @param int $numLocalEdits
	 * @param int $numRecentEdits
	 * @param int $numDeletedEdits
	 */
	public function __construct(
		int $numLocalEdits = 0,
		int $numRecentEdits = 0,
		int $numDeletedEdits = 0
	) {
		$this->numLocalEdits = $numLocalEdits;
		$this->numRecentEdits = $numRecentEdits;
		$this->numDeletedEdits = $numDeletedEdits;
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

	/**
	 * @return int
	 */
	public function getNumDeletedEdits(): int {
		return $this->numDeletedEdits;
	}
}
