<?php

namespace MediaWiki\IPInfo\InfoRetriever;

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
	 * Retrieve info about an IP address.
	 *
	 * @param string $ip
	 * @return mixed
	 */
	public function retrieveFromIP( string $ip );
}
