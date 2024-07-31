<?php

namespace MediaWiki\IPInfo;

use MediaWiki\IPInfo\InfoRetriever\InfoRetriever;
use MediaWiki\User\UserIdentity;
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
	 * @param UserIdentity|string $user
	 * @return array
	 */
	public function retrieveFor( $user ): array {
		$data = [];

		if ( is_string( $user ) ) {
			$user = new UserIdentityValue( 0, $user );
		}

		foreach ( $this->retrievers as $retriever ) {
			$data[$retriever->getName()] = $retriever->retrieveFor( $user );
		}

		$subjectName = $user->getName();

		return [
			// Ensure we consistently format the subject name if it is an IP address
			// belonging to an anonymous user.
			'subject' => IPUtils::isIPAddress( $subjectName ) ? IPUtils::prettifyIP( $subjectName ) : $subjectName,
			'data' => $data,
		];
	}
}
