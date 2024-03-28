<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use MediaWiki\IPInfo\Info\ContributionInfo;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;

class ContributionInfoRetriever implements InfoRetriever {
	private IConnectionProvider $dbProvider;

	public function __construct( IConnectionProvider $dbProvider ) {
		$this->dbProvider = $dbProvider;
	}

	/** @inheritDoc */
	public function getName(): string {
		return 'ipinfo-source-contributions';
	}

	/** @inheritDoc */
	public function retrieveFromIP( string $ip ): ContributionInfo {
		$hexIP = IPUtils::toHex( $ip );

		$dbr = $this->dbProvider->getReplicaDatabase();
		$numLocalEdits = $dbr->newSelectQueryBuilder()
			->from( 'ip_changes' )
			->where( [
					'ipc_hex' => $hexIP,
				]
			)
			->caller( __METHOD__ )
			->fetchRowCount();

		$oneDayTS = (int)wfTimestamp( TS_UNIX ) - ( 24 * 60 * 60 );
		$numRecentEdits = $dbr->newSelectQueryBuilder()
			->from( 'ip_changes' )
			->where( [
					'ipc_hex' => $hexIP,
					$dbr->expr( 'ipc_rev_timestamp', '>', $dbr->timestamp( $oneDayTS ) ),
				]
			)
			->caller( __METHOD__ )
			->fetchRowCount();

		$numDeletedEdits = $dbr->newSelectQueryBuilder()
			->from( 'archive' )
			->join( 'actor', null, 'actor_id=ar_actor' )
			->where( [
					'actor_name' => $ip,
				]
			)
			->caller( __METHOD__ )
			->fetchRowCount();

		return new ContributionInfo( $numLocalEdits, $numRecentEdits, $numDeletedEdits );
	}
}
