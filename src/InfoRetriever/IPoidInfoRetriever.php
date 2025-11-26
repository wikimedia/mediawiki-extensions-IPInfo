<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use MediaWiki\Extension\IPReputation\IPoid\IPoidResponse;
use MediaWiki\Extension\IPReputation\Services\IPReputationIPoidDataLookup;
use MediaWiki\User\UserIdentity;

/**
 * Manager for getting information from the iPoid service.
 */
class IPoidInfoRetriever extends BaseInfoRetriever {

	public const NAME = 'ipinfo-source-ipoid';

	public function __construct(
		private readonly IPReputationIPoidDataLookup $ipReputationIPoidDataLookup
	) {
	}

	/** @inheritDoc */
	public function getName(): string {
		return self::NAME;
	}

	/**
	 * @inheritDoc
	 * @return IPoidResponse
	 */
	public function retrieveFor( UserIdentity $user, ?string $ip ): IPoidResponse {
		if ( $ip === null ) {
			return IPoidResponse::newFromArray( [] );
		}
		return $this->ipReputationIPoidDataLookup->getIPoidDataForIp( $ip, __METHOD__ ) ??
			IPoidResponse::newFromArray( [] );
	}

	/**
	 * Retrieve IP information for the given IPs from the IPoid service.
	 * @param UserIdentity $user
	 * @param string[] $ips IP addresses in human-readable form
	 * @return IPoidResponse[] Map of IPoidResponse instances keyed by IP address
	 */
	public function retrieveBatch( UserIdentity $user, array $ips ): array {
		$infoByIp = [];
		foreach ( $ips as $ip ) {
			// TODO: When fully migrated to OpenSearch IPoid backend, use a new method in Extension:IPReputation
			// to query for multiple "ip" terms at once. See T415176
			$infoByIp[$ip] = $this->ipReputationIPoidDataLookup->getIPoidDataForIp( $ip, __METHOD__ ) ??
				IPoidResponse::newFromArray( [] );
		}
		return $infoByIp;
	}

}
