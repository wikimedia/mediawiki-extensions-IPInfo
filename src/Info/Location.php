<?php

namespace MediaWiki\IPInfo\Info;

class Location {
	/** @var int */
	private $id;

	/** @var string */
	private $label;

	/**
	 * @param int $id
	 * @param string $label
	 */
	public function __construct(
		int $id,
		string $label
	) {
		$this->id = $id;
		$this->label = $label;
	}

	/**
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getLabel(): string {
		return $this->label;
	}
}
