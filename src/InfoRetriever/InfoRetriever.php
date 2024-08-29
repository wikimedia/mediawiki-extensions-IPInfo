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
}
