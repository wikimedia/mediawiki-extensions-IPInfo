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
	 * @param string|null $ip The IP address used by the user being looked up,
	 * or `null` if this data was not available.
	 * @return array
	 */
	public function retrieveFor( $user, ?string $ip ): array {
		$data = [];

		if ( is_string( $user ) ) {
			$user = new UserIdentityValue( 0, $user );
		}

		foreach ( $this->retrievers as $retriever ) {
			$data[$retriever->getName()] = $retriever->retrieveFor( $user, $ip );
		}

		$subjectName = $user->getName();

		return [
			// Ensure we consistently format the subject name if it is an IP address
			// belonging to an anonymous user.
			'subject' => IPUtils::isIPAddress( $subjectName ) ? IPUtils::prettifyIP( $subjectName ) : $subjectName,
			'data' => $data,
		];
	}

	/**
	 * Retrieve IP information for a set of IPs associated with a temporary user.
	 *
	 * @param UserIdentity $user
	 * @param string[] $ips The IP addresses to retrieve information for, in human-readable form.
	 * @param string[]|null $retrieverNames Names of the InfoRetrievers whose data should be included in
	 * the result, or `null` to include data from every registered InfoRetriever.
	 * @return array[] IP information in the format returned by {@link InfoManager::retrieveFor()},
	 * keyed by IP address.
	 */
	public function retrieveBatch(
		UserIdentity $user,
		array $ips,
		?array $retrieverNames = null
	): array {
		$subjectName = IPUtils::isIPAddress( $user->getName() ) ? IPUtils::prettifyIP( $user->getName() ) :
			$user->getName();

		$infoByIp = array_fill_keys( $ips, [
			'subject' => $subjectName,
			'data' => []
		] );

		foreach ( $this->retrievers as $retriever ) {
			if ( $retrieverNames === null || in_array( $retriever->getName(), $retrieverNames ) ) {
				$batch = $retriever->retrieveBatch( $user, $ips );
				foreach ( $batch as $ip => $data ) {
					$infoByIp[$ip]['data'][$retriever->getName()] = $data;
				}
			}
		}

		return $infoByIp;
	}
}
