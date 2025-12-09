<?php
namespace MediaWiki\IPInfo;

use MapCacheLRU;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserIdentityUtils;
use Psr\Log\LoggerInterface;
use Wikimedia\Assert\Assert;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Check if an IP is known to the wiki by checking if it has an entry
 * in either CheckUser or AbuseFilter. IPs with revisions are guaranteed
 * to be known/logged and CU/AF log checks will cover IPs that attempted to
 * act but whose actions were either reverted or blocked.
 */
class AnonymousUserIPLookup {
	private readonly MapCacheLRU $recentAddressCache;

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly UserIdentityUtils $userIdentityUtils,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly LoggerInterface $logger,
	) {
		$this->recentAddressCache = new MapCacheLRU( 8 );
	}

	public function checkIPIsKnown( string $ip ): bool {
		Assert::parameter(
			IPUtils::isValid( $ip ),
			'$ip',
			'must be a valid IP and cannot be a range'
		);
		$ip = IPUtils::sanitizeIP( $ip );

		// Return early false if neither extensions to be looked up are loaded
		// as by definition the IP cannot be known
		if (
			!$this->extensionRegistry->isLoaded( 'CheckUser' ) &&
			!$this->extensionRegistry->isLoaded( 'Abuse Filter' )
		) {
			return false;
		}

		$dbr = $this->connectionProvider->getReplicaDatabase();

		// Return true if any results are found - an entry assures the IP is known
		if ( $this->extensionRegistry->isLoaded( 'CheckUser' ) ) {
			$latestChange = $dbr->newSelectQueryBuilder()
				->select( '1' )
				->from( 'cu_changes' )
				// T338276
				->useIndex( 'cuc_actor_ip_hex_time' )
				->join( 'actor', null, 'cuc_actor=actor_id' )
				->where( [
					'actor_name' => $ip,
				] )
				->limit( 1 )
				->caller( __METHOD__ )
				->fetchRow();
			if ( $latestChange ) {
				return true;
			}

			$latestLogEvent = $dbr->newSelectQueryBuilder()
				->select( '1' )
				->from( 'cu_log_event' )
				// T338276
				->useIndex( 'cule_actor_ip_hex_time' )
				->join( 'actor', null, 'cule_actor=actor_id' )
				->where( [
					'actor_name' => $ip,
				] )
				->limit( 1 )
				->caller( __METHOD__ )
				->fetchRow();
			if ( $latestLogEvent ) {
				return true;
			}

			$actorId = $dbr->newSelectQueryBuilder()
				->select( 'actor_id' )
				->from( 'actor' )
				->where( [
					'actor_name' => $ip,
				] )
				->limit( 1 )
				->caller( __METHOD__ )
				->fetchField();

			if ( $actorId ) {
				$latestPrivateEventWithActor = $dbr->newSelectQueryBuilder()
					->select( '1' )
					->from( 'cu_private_event' )
					// T338276
					->useIndex( 'cupe_actor_ip_hex_time' )
					->where(
						$dbr->expr( 'cupe_actor', '=', $actorId )
					)
					->limit( 1 )
					->caller( __METHOD__ )
					->fetchRow();
				if ( $latestPrivateEventWithActor ) {
					return true;
				}
			}

			$latestPrivateEvent = $dbr->newSelectQueryBuilder()
				->select( '1' )
				->from( 'cu_private_event' )
				// T338276
				->useIndex( 'cupe_actor_ip_hex_time' )
				->where(
					$dbr->expr( 'cupe_actor', '=', null )
						->and( 'cupe_ip_hex', '=', IPUtils::toHex( $ip ) )
				)
				->limit( 1 )
				->caller( __METHOD__ )
				->fetchRow();
			if ( $latestPrivateEvent ) {
				return true;
			}
		}

		if ( $this->extensionRegistry->isLoaded( 'Abuse Filter' ) ) {
			$latestAbuseFilterHit = $dbr->newSelectQueryBuilder()
				->select( '1' )
				->from( 'abuse_filter_log' )
				->where( [
					'afl_user_text' => $ip,
					'afl_user' => 0,
				] )
				->useIndex( 'afl_user_timestamp' )
				->limit( 1 )
				->caller( __METHOD__ )
				->fetchRow();
			if ( $latestAbuseFilterHit ) {
				return true;
			}
		}

		// If no entries were found, IP is not known
		return false;
	}
}
