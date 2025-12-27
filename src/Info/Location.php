<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Location implements JsonSerializable {
	public function __construct(
		private readonly int $id,
		private readonly string $label,
	) {
	}

	public function getId(): int {
		return $this->id;
	}

	public function getLabel(): string {
		return $this->label;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'label' => $this->getLabel(),
		];
	}
}
