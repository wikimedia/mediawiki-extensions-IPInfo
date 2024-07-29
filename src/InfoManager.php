<?php

namespace MediaWiki\IPInfo;

use MediaWiki\IPInfo\InfoRetriever\InfoRetriever;
use MediaWiki\User\UserIdentityValue;
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
		$user = new UserIdentityValue( 0, $ip );

		foreach ( $this->retrievers as $retriever ) {
			$data[$retriever->getName()] = $retriever->retrieveFor( $user );
		}

		return [
			'subject' => IPUtils::prettifyIP( $ip ),
			'data' => $data,
		];
	}
}
