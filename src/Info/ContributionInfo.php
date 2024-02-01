<?php

namespace MediaWiki\IPInfo\Info;

class ContributionInfo {
	private int $numLocalEdits;

	private int $numRecentEdits;
	private int $numDeletedEdits;

	public function __construct(
		int $numLocalEdits = 0,
		int $numRecentEdits = 0,
		int $numDeletedEdits = 0
	) {
		$this->numLocalEdits = $numLocalEdits;
		$this->numRecentEdits = $numRecentEdits;
		$this->numDeletedEdits = $numDeletedEdits;
	}

	public function getNumLocalEdits(): int {
		return $this->numLocalEdits;
	}

	public function getNumRecentEdits(): int {
		return $this->numRecentEdits;
	}

	public function getNumDeletedEdits(): int {
		return $this->numDeletedEdits;
	}
}
