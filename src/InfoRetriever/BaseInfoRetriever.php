<?php
namespace MediaWiki\IPInfo\InfoRetriever;

use MediaWiki\User\UserIdentity;

abstract class BaseInfoRetriever implements InfoRetriever {

	/**
	 * Retrieve IP information for a set of IPs associated with a temporary user.
	 * This is a default implementation that simply retrieves the information sequentially.
	 *
	 * @param UserIdentity $user
	 * @param string[] $ips The IP addresses to retrieve information for, in human-readable form.
	 * @return array Map of IP information keyed by IP address.
	 */
	public function retrieveBatch( UserIdentity $user, array $ips ): array {
		$infoMap = [];

		foreach ( $ips as $ip ) {
			$infoMap[$ip] = $this->retrieveFor( $user, $ip );
		}

		return $infoMap;
	}
}
