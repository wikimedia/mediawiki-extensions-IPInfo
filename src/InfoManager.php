<?php

namespace MediaWiki\IPInfo;

use MediaWiki\IPInfo\InfoRetriever\InfoRetriever;
use Wikimedia\IPUtils;

class InfoManager {
	/** @var InfoRetriever[] */
	private $retrievers;

	/**
	 * @param InfoRetriever[] $retrievers
	 */
	public function __construct(
		array $retrievers
	) {
		$this->retrievers = $retrievers;
	}

	/**
	 * Retrieve info about an IP address.
	 *
	 * @param string $ip
	 * @return mixed[]
	 */
	public function retrieveFromIP( string $ip ): array {
		$data = [];

		foreach ( $this->retrievers as $retriever ) {
			$data[$retriever->getName()] = $retriever->retrieveFromIP( $ip );
		}

		$ip = IPUtils::prettifyIP( $ip );

		return [
			'subject' => $ip,
			'data' => $data,
		];
	}
}
