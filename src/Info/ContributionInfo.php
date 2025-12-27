<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class ContributionInfo implements JsonSerializable {
	public function __construct(
		private readonly int $numLocalEdits = 0,
		private readonly int $numRecentEdits = 0,
		private readonly int $numDeletedEdits = 0,
	) {
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

	public function jsonSerialize(): array {
		return [
			'numLocalEdits' => $this->getNumLocalEdits(),
			'numRecentEdits' => $this->getNumRecentEdits(),
			'numDeletedEdits' => $this->getNumDeletedEdits(),
		];
	}
}
