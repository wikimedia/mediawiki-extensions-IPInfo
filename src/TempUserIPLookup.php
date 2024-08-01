<?php
namespace MediaWiki\IPInfo;

use ExtensionRegistry;
use MapCacheLRU;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use Wikimedia\Assert\Assert;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Provides access to IP addresses associated with anonymous or temporary user accounts.
 *
 * For temporary users, the CheckUser extension is required to store and access the IP addresses
 * they used while performing actions on the wiki. This class encapsulates the necessary access
 * to CheckUser database tables, returning no data if CheckUser is unavailable.
 */
class TempUserIPLookup {
	private IConnectionProvider $connectionProvider;
	private ExtensionRegistry $extensionRegistry;
	private MapCacheLRU $recentAddressCache;
	private UserIdentityUtils $userIdentityUtils;

	public function __construct(
		IConnectionProvider $connectionProvider,
		UserIdentityUtils $userIdentityUtils,
		ExtensionRegistry $extensionRegistry
	) {
		$this->connectionProvider = $connectionProvider;
		$this->userIdentityUtils = $userIdentityUtils;
		$this->extensionRegistry = $extensionRegistry;
		$this->recentAddressCache = new MapCacheLRU( 8 );
	}

	/**
	 * Get the IP address most recently used by the given anonymous or temporary user.
	 *
	 * @param UserIdentity $user The user to fetch the most recent IP address for.
	 * @return string|null The IP address most recently used by the user in human-readable form,
	 * or `null` if this data is not available.
	 */
	public function getMostRecentAddress( UserIdentity $user ): ?string {
		Assert::parameter(
			!$this->userIdentityUtils->isNamed( $user ),
			'$user',
			'must be an anonymous or temporary user'
		);

		// Anonymous users are identified by their own IP address, so simply return that.
		if ( !$this->userIdentityUtils->isTemp( $user ) ) {
			return $user->getName();
		}

		if ( !$this->extensionRegistry->isLoaded( 'CheckUser' ) ) {
			return null;
		}

		// Avoid duplicate queries from disparate info retrievers.
		$cachedValue = $this->recentAddressCache->get( $user->getName(), INF, false );
		if ( $cachedValue !== false ) {
			return $cachedValue;
		}

		$dbr = $this->connectionProvider->getReplicaDatabase();
		$address = $dbr->newSelectQueryBuilder()
			->select( 'cuc_ip' )
			->from( 'cu_changes' )
			// T338276
			->useIndex( 'cuc_actor_ip_time' )
			->join( 'actor', null, 'cuc_actor=actor_id' )
			->where( [
				'actor_name' => $user->getName(),
			] )
			->orderBy( 'cuc_timestamp', SelectQueryBuilder::SORT_DESC )
			->limit( 1 )
			->caller( __METHOD__ )
			->fetchField();

		$address = $address ?: null;
		$this->recentAddressCache->set( $user->getName(), $address );

		return $address;
	}

	/**
	 * Get the count of unique IP addresses used by the given temporary user.
	 * @param UserIdentity $user The user to fetch the count of used unique IP address for.
	 * @return int|null The count of unique IP addresses used by this user, or `null`
	 * if this data is not available.
	 */
	public function getDistinctAddressCount( UserIdentity $user ): ?int {
		Assert::parameter(
			$this->userIdentityUtils->isTemp( $user ),
			'$user',
			'must be a temporary user'
		);

		if ( !$this->extensionRegistry->isLoaded( 'CheckUser' ) ) {
			return null;
		}

		$dbr = $this->connectionProvider->getReplicaDatabase();

		$count = $dbr->newSelectQueryBuilder()
			->select( 'COUNT(DISTINCT cuc_ip)' )
			->from( 'cu_changes' )
			->join( 'actor', null, 'cuc_actor=actor_id' )
			->where( [
				'actor_name' => $user->getName(),
			] )
			->caller( __METHOD__ )
			->fetchField();

		return $count ?: 0;
	}
}
