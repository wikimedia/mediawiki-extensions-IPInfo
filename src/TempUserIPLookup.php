<?php
namespace MediaWiki\IPInfo;

use MapCacheLRU;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use Psr\Log\LoggerInterface;
use Wikimedia\Assert\Assert;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Rdbms\Subquery;

/**
 * Provides access to IP addresses associated with anonymous or temporary user accounts.
 *
 * For temporary users, the CheckUser extension is required to store and access the IP addresses
 * they used while performing actions on the wiki. This class encapsulates the necessary access
 * to CheckUser database tables, returning no data if CheckUser is unavailable.
 */
class TempUserIPLookup {
	public const CONSTRUCTOR_OPTIONS = [
		'IPInfoMaxDistinctIPResults'
	];

	private IConnectionProvider $connectionProvider;
	private ExtensionRegistry $extensionRegistry;
	private MapCacheLRU $recentAddressCache;
	private UserIdentityUtils $userIdentityUtils;
	private LoggerInterface $logger;
	private ServiceOptions $serviceOptions;

	public function __construct(
		IConnectionProvider $connectionProvider,
		UserIdentityUtils $userIdentityUtils,
		ExtensionRegistry $extensionRegistry,
		LoggerInterface $logger,
		ServiceOptions $serviceOptions
	) {
		$serviceOptions->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		Assert::parameter(
			is_int( $serviceOptions->get( 'IPInfoMaxDistinctIPResults' ) ) &&
			$serviceOptions->get( 'IPInfoMaxDistinctIPResults' ) > 0,
			'$serviceOptions',
			'IPInfoMaxDistinctIPResults must be a positive integer'
		);

		$this->connectionProvider = $connectionProvider;
		$this->userIdentityUtils = $userIdentityUtils;
		$this->extensionRegistry = $extensionRegistry;
		$this->recentAddressCache = new MapCacheLRU( 8 );
		$this->logger = $logger;
		$this->serviceOptions = $serviceOptions;
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

		// Return early if neither extensions to look data up in are loaded
		if (
			!$this->extensionRegistry->isLoaded( 'CheckUser' ) &&
			!$this->extensionRegistry->isLoaded( 'Abuse Filter' )
		) {
			return null;
		}

		// Avoid duplicate queries from disparate info retrievers.
		$cachedValue = $this->recentAddressCache->get( $user->getName(), INF, false );
		if ( $cachedValue !== false ) {
			return $cachedValue;
		}

		$dbr = $this->connectionProvider->getReplicaDatabase();

		// Capture the latest found log entry. Subsequent queries will use this
		// timestamp to look for more recent entries than the previous query.
		$latestHit = [
			'ip' => null,
			'timestamp' => 0,
		];

		if ( $this->extensionRegistry->isLoaded( 'CheckUser' ) ) {
			$latestChange = $dbr->newSelectQueryBuilder()
				->select( [ 'cuc_ip', 'cuc_timestamp' ] )
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
				->fetchRow();
			if ( $latestChange ) {
				$latestHit['ip'] = $latestChange->cuc_ip;
				$latestHit['timestamp'] = $latestChange->cuc_timestamp;
			}

			$latestLogEvent = $dbr->newSelectQueryBuilder()
				->select( [ 'cule_ip', 'cule_timestamp' ] )
				->from( 'cu_log_event' )
				// T338276
				->useIndex( 'cule_actor_ip_time' )
				->join( 'actor', null, 'cule_actor=actor_id' )
				->where( [
					'actor_name' => $user->getName(),
					$dbr->expr( 'cule_timestamp', '>', $latestHit['timestamp'] ),
				] )
				->orderBy( 'cule_timestamp', SelectQueryBuilder::SORT_DESC )
				->limit( 1 )
				->caller( __METHOD__ )
				->fetchRow();
			if ( $latestLogEvent ) {
				$latestHit['ip'] = $latestLogEvent->cule_ip;
				$latestHit['timestamp'] = $latestLogEvent->cule_timestamp;
			}

			$latestPrivateEvent = $dbr->newSelectQueryBuilder()
				->select( [ 'cupe_ip', 'cupe_timestamp' ] )
				->from( 'cu_private_event' )
				// T338276
				->useIndex( 'cupe_actor_ip_time' )
				->join( 'actor', null, 'cupe_actor=actor_id' )
				->where( [
					'actor_name' => $user->getName(),
					$dbr->expr( 'cupe_timestamp', '>', $latestHit['timestamp'] ),
				] )
				->orderBy( 'cupe_timestamp', SelectQueryBuilder::SORT_DESC )
				->limit( 1 )
				->caller( __METHOD__ )
				->fetchRow();
			if ( $latestPrivateEvent ) {
				$latestHit['ip'] = $latestPrivateEvent->cupe_ip;
				$latestHit['timestamp'] = $latestPrivateEvent->cupe_timestamp;
			}
		}

		if ( $this->extensionRegistry->isLoaded( 'Abuse Filter' ) ) {
			$latestAbuseFilterHit = $dbr->newSelectQueryBuilder()
				->select( [ 'afl_ip_hex', 'afl_timestamp' ] )
				->from( 'abuse_filter_log' )
				->where( [
					'afl_user_text' => $user->getName(),
					$dbr->expr( 'afl_ip_hex', '!=', '\'\'' ),
					$dbr->expr( 'afl_timestamp', '>', $latestHit['timestamp'] ),
				] )
				->useIndex( 'afl_ip_timestamp' )
				->orderBy( 'afl_timestamp', SelectQueryBuilder::SORT_DESC )
				->limit( 1 )
				->caller( __METHOD__ )
				->fetchRow();
			if ( $latestAbuseFilterHit ) {
				$latestHit['ip'] = IPUtils::formatHex( $latestAbuseFilterHit->afl_ip_hex );
				$latestHit['timestamp'] = $latestAbuseFilterHit->afl_timestamp;
			}
		}

		$this->recentAddressCache->set( $user->getName(), $latestHit['ip'] );
		return $latestHit['ip'];
	}

	/**
	 * Get the IP address used by the author of the given revision while creating the revision.
	 *
	 * @param RevisionRecord $revision
	 * @return string|null The IP address used by the author in human-readable form,
	 *  or `null` if this data is not available.
	 */
	public function getAddressForRevision( RevisionRecord $revision ): ?string {
		$performer = $revision->getUser( $revision::RAW );
		Assert::parameter(
			!$this->userIdentityUtils->isNamed( $performer ),
			'$revision',
			'must be authored by an anonymous or temporary user'
		);

		// Anonymous users are identified by their own IP address, so simply return that.
		if ( !$this->userIdentityUtils->isTemp( $performer ) ) {
			return $performer->getName();
		}

		if ( !$this->extensionRegistry->isLoaded( 'CheckUser' ) ) {
			return null;
		}

		$dbr = $this->connectionProvider->getReplicaDatabase();
		$ip = $dbr->newSelectQueryBuilder()
			->select( 'cuc_ip_hex' )
			->from( 'cu_changes' )
			->join( 'actor', null, 'cuc_actor=actor_id' )
			->where( [
				'actor_name' => $performer->getName(),
				'cuc_this_oldid' => $revision->getId(),
			] )
			->caller( __METHOD__ )
			->fetchField();

		return $ip ? IPUtils::formatHex( $ip ) : null;
	}

	/**
	 * Get the IP address used by the performer of the given log entry when the log entry was created.
	 *
	 * @param DatabaseLogEntry $logEntry
	 * @return string|null The IP address used by the performer in human-readable form,
	 * or `null` if this data is not available.
	 */
	public function getAddressForLogEntry( DatabaseLogEntry $logEntry ): ?string {
		$performer = $logEntry->getPerformerIdentity();
		Assert::parameter(
			!$this->userIdentityUtils->isNamed( $performer ),
			'$logEntry',
			'performer must be an anonymous or temporary user'
		);

		// Anonymous users are identified by their own IP address, so simply return that.
		if ( !$this->userIdentityUtils->isTemp( $performer ) ) {
			return $performer->getName();
		}

		if ( !$this->extensionRegistry->isLoaded( 'CheckUser' ) ) {
			return null;
		}

		$dbr = $this->connectionProvider->getReplicaDatabase();
		$ip = $dbr->newSelectQueryBuilder()
			->select( 'cule_ip_hex' )
			->from( 'cu_log_event' )
			->join( 'actor', null, 'cule_actor=actor_id' )
			->where( [
				'actor_name' => $performer->getName(),
				'cule_log_id' => $logEntry->getId(),
			] )
			->caller( __METHOD__ )
			->fetchField();

		return $ip ? IPUtils::formatHex( $ip ) : null;
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

		$distinctCuChangesIPs = $dbr->newSelectQueryBuilder()
			->select( 'DISTINCT cuc_ip' )
			->from( 'cu_changes' )
			->join( 'actor', null, 'cuc_actor=actor_id' )
			->where( [
				'actor_name' => $user->getName(),
			] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		$cuLogQuery = $dbr->newSelectQueryBuilder()
			->select( 'DISTINCT cule_ip' )
			->from( 'cu_log_event' )
			->join( 'actor', null, 'cule_actor=actor_id' )
			->where( [
				'actor_name' => $user->getName()
			] )
			->caller( __METHOD__ );

		if ( count( $distinctCuChangesIPs ) > 0 ) {
			$cuLogQuery->andWhere( $dbr->expr( 'cule_ip', '!=', $distinctCuChangesIPs ) );
		}

		$distinctCuLogEventIPs = $cuLogQuery->fetchFieldValues();

		return count( $distinctCuChangesIPs ) + count( $distinctCuLogEventIPs );
	}

	/**
	 * Get information about distinct IP addresses used by a temporary user
	 * in the last $wgCUDMaxAge seconds. At most $wgIPInfoMaxDistinctIPResults IPs will be returned.
	 *
	 * This currently does not include IP addresses only associated with private CU events,
	 * since no mechanism exists yet to reveal such IP addresses.
	 *
	 * @param UserIdentity $user The temporary user to fetch IP usage for.
	 * @return TempUserIPRecord[] Map of IP usage information keyed by IP address
	 */
	public function getDistinctIPInfo( UserIdentity $user ): array {
		Assert::parameter(
			$this->userIdentityUtils->isTemp( $user ),
			'$user',
			'must be a temporary user'
		);

		if ( !$this->extensionRegistry->isLoaded( 'CheckUser' ) ) {
			return [];
		}

		$dbr = $this->connectionProvider->getReplicaDatabase();

		// Fetch a revision or log ID for each distinct IP so that they can be used to reveal the IP.
		// These need to be subqueries, since we want each query to return one row per used IP
		// and therefore cannot GROUP BY these columns.
		// Note that these IDs are only intended to be used to support IP reveal functionality, so the
		// queries need not be deterministic across multiple invocations with the same actor ID and IP
		// combination -- it is sufficient for them to return *any* revision or log ID matching that actor
		// ID and IP combination.
		$oldIdQuery = $dbr->newSelectQueryBuilder()
			->select( 'cuc_this_oldid' )
			->from( 'cu_changes', 'cuc' )
			->where( [
				'cuc.cuc_actor=actor',
				'cuc.cuc_ip=ip'
			] )
			->limit( 1 );

		$logIdQuery = $dbr->newSelectQueryBuilder()
			->select( 'cule_log_id' )
			->from( 'cu_log_event', 'cule' )
			->where( [
				'cule.cule_actor=actor',
				'cule.cule_ip=ip'
			] )
			->limit( 1 );

		// Set a limit for both the cu_changes and cu_log_event queries to avoid unbounded reads
		// while still being higher than the likely maximum distinct IP count of temporary users.
		// Provide a separate, lower, warning threshold that triggers logging to allow gauging
		// whether this query may need to be reimplemented as a paginated query.
		$queryLimit = $this->serviceOptions->get( 'IPInfoMaxDistinctIPResults' );
		$warnThreshold = (int)( $queryLimit / 2 );

		$res = $dbr->newUnionQueryBuilder()
			->add(
				$dbr->newSelectQueryBuilder()
					->select( [
						'ip' => 'cuc_ip',
						'actor' => 'cuc_actor',
						'rev_id' => new Subquery( $oldIdQuery->getSQL() ),
						'NULL as log_id',
					] )
					->from( 'cu_changes' )
					->join( 'actor', null, 'cuc_actor = actor_id' )
					->where( [ 'actor_name' => $user->getName() ] )
					->groupBy( [ 'cuc_actor', 'cuc_ip' ] )
					->limit( $queryLimit )
			)
			->add(
				$dbr->newSelectQueryBuilder()
					->select( [
						'ip' => 'cule_ip',
						'actor' => 'cule_actor',
						'NULL as rev_id',
						'log_id' => new Subquery( $logIdQuery->getSQL() )
					] )
					->from( 'cu_log_event' )
					->join( 'actor', null, 'cule_actor = actor_id' )
					->where( [ 'actor_name' => $user->getName() ] )
					->groupBy( [ 'cule_actor', 'cule_ip' ] )
					->limit( $queryLimit )
			)
			->caller( __METHOD__ )
			->fetchResultSet();

		if ( $res->numRows() >= $warnThreshold ) {
			$this->logger->warning( __METHOD__ . ': high number of results returned.' );
		}

		$recordsByIp = [];
		foreach ( $res as $row ) {
			if ( count( $recordsByIp ) >= $queryLimit ) {
				break;
			}

			$recordsByIp[$row->ip] = new TempUserIPRecord(
				$row->ip,
				$row->rev_id ?? null,
				$row->log_id ?? null
			);
		}

		return $recordsByIp;
	}
}
