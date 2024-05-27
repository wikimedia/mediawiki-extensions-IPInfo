<?php

namespace MediaWiki\IPInfo\Info;

use JsonSerializable;

class Location implements JsonSerializable {
	private int $id;

	private string $label;

	public function __construct(
		int $id,
		string $label
	) {
		$this->id = $id;
		$this->label = $label;
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
