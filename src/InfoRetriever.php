<?php

namespace MediaWiki\IPInfo;

use MediaWiki\IPInfo\Info\Info;

interface InfoRetriever {
	/**
	 * Retrieve info about an IP address.
	 *
	 * @param string $ip
	 * @return Info
	 */
	public function retrieveFromIP( string $ip ): Info;
}
