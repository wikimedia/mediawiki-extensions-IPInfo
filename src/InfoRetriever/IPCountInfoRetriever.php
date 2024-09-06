<?php
namespace MediaWiki\IPInfo\InfoRetriever;

use MediaWiki\IPInfo\Info\IPCountInfo;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\User\UserIdentity;

/**
 * Retrieves the number of unique IP addresses used by a temporary user account.
 */
class IPCountInfoRetriever extends BaseInfoRetriever {

	public const NAME = 'ipinfo-source-ip-count';

	private TempUserIPLookup $tempUserIPLookup;

	public function __construct( TempUserIPLookup $tempUserIPLookup ) {
		$this->tempUserIPLookup = $tempUserIPLookup;
	}

	public function getName(): string {
		return self::NAME;
	}

	public function retrieveFor( UserIdentity $user, ?string $ip ): IPCountInfo {
		// Showing the count of unique IP addresses only makes sense for temporary users,
		// since anonymous users are identified by their IP address and therefore by definition
		// have a unique IP address count of 1.
		// So, don't attempt to fetch or show this data for them.
		if ( !$user->isRegistered() ) {
			return new IPCountInfo( null );
		}

		$count = $this->tempUserIPLookup->getDistinctAddressCount( $user );
		return new IPCountInfo( $count );
	}
}
