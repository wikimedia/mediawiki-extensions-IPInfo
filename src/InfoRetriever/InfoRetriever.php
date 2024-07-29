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
	 * TODO: Temporary user handling is yet to be implemented (T349716))
	 *
	 * @param UserIdentity $user
	 * @return mixed
	 */
	public function retrieveFor( UserIdentity $user );
}
