<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use MediaWiki\User\UserIdentity;

interface InfoRetriever {

	/**
	 * Gets the name of the retriever.
	 *
	 * The name of a retriever must be readable by humans and machines and must be unique.
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Retrieve info about an anonymous user or temporary user account.
	 *
	 * @param UserIdentity $user
	 * @param string|null $ip The IP address used by the user being looked up,
	 * or `null` if this data was not available.
	 * @return mixed
	 */
	public function retrieveFor( UserIdentity $user, ?string $ip );

	/**
	 * Retrieve IP information for a set of IPs associated with a temporary user.
	 *
	 * @param UserIdentity $user
	 * @param string[] $ips The IP addresses to retrieve information for, in human-readable form.
	 * @return array Map of IP information keyed by IP address.
	 */
	public function retrieveBatch( UserIdentity $user, array $ips ): array;
}
