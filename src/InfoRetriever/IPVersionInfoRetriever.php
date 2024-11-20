<?php
namespace MediaWiki\IPInfo\InfoRetriever;

use MediaWiki\IPInfo\Info\IPVersionInfo;
use MediaWiki\User\UserIdentity;
use Wikimedia\IPUtils;

class IPVersionInfoRetriever extends BaseInfoRetriever {
	public const NAME = 'ipinfo-source-ipversion';

	public function getName(): string {
		return self::NAME;
	}

	public function retrieveFor( UserIdentity $user, ?string $ip ): IPVersionInfo {
		if ( $ip !== null ) {
			if ( IPUtils::isIPv4( $ip ) ) {
				return new IPVersionInfo( 'ipv4' );
			}

			if ( IPUtils::isIPv6( $ip ) ) {
				return new IPVersionInfo( 'ipv6' );
			}
		}

		return new IPVersionInfo( null );
	}
}
