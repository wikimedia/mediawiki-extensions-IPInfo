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
	 * TODO: Make this return a domain object, e.g. InfoManagerResponse.
	 *
	 * @param string $ip
	 * @return array
	 */
	public function retrieveFromIP( string $ip ): array {
		$data = [];

		foreach ( $this->retrievers as $retriever ) {
			$data[$retriever->getName()] = $retriever->retrieveFromIP( $ip );
		}

		return [
			'subject' => IPUtils::prettifyIP( $ip ),
			'data' => $data,
		];
	}
}
