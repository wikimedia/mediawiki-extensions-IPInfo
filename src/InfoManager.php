<?php

namespace MediaWiki\IPInfo;

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
	public function retrieveFromIP( string $ip ) : array {
		$data = [];

		foreach ( $this->retrievers as $retriever ) {
			$data[] = $retriever->retrieveFromIP( $ip );
		}

		return [
			'subject' => $ip,
			'data' => $data,
		];
	}
}
