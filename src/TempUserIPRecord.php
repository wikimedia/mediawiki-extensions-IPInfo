<?php
namespace MediaWiki\IPInfo;

use Wikimedia\Assert\Assert;
use Wikimedia\Assert\PreconditionException;

/**
 * Holds information about an IP address used by a temporary account.
 */
class TempUserIPRecord {
	private string $ip;

	private ?int $revisionId;

	private ?int $logId;

	/**
	 * Initialize a new TempUserIPRecord instance with the given parameters.
	 *
	 * @param string $ip The referenced IP address in human-readable form.
	 * @param int|null $revisionId The revision ID this IP address is associated with,
	 * or `null` if there is no associated revision.
	 * @param int|null $logId The log entry ID this IP address is associated with,
	 * or `null` if there is no associated log entry.
	 *
	 * @throws PreconditionException If both $revisionId and $logId are `null`.
	 */
	public function __construct( string $ip, ?int $revisionId, ?int $logId ) {
		Assert::precondition(
			$revisionId !== null || $logId !== null,
			'Either the $revisionId or the $logId parameter must be non-null'
		);
		$this->ip = $ip;
		$this->revisionId = $revisionId;
		$this->logId = $logId;
	}

	/**
	 * The IP address in human-readable form.
	 * @return string
	 */
	public function getIp(): string {
		return $this->ip;
	}

	/**
	 * The revision ID this IP address is associated with, or `null` if there is no associated revision.
	 * @return int|null
	 */
	public function getRevisionId(): ?int {
		return $this->revisionId;
	}

	/**
	 * The log entry ID this IP address is associated with, or `null` if there is no associated log entry.
	 * @return int|null
	 */
	public function getLogId(): ?int {
		return $this->logId;
	}
}
