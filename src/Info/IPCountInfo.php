<?php
namespace MediaWiki\IPInfo\Info;

/**
 * Holds the count of IP addresses associated with a temporary user.
 */
class IPCountInfo {

	public function __construct(
		private readonly ?int $count,
	) {
	}

	/**
	 * Get the number of unique IP addresses associated with a temporary user,
	 * or `null` if this data could not be found.
	 * @return int|null
	 */
	public function getCount(): ?int {
		return $this->count;
	}
}
