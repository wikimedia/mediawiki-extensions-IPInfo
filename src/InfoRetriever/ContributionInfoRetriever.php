<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use MediaWiki\IPInfo\Info\ContributionInfo;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IReadableDatabase;

class ContributionInfoRetriever implements InfoRetriever {
	/** @var IReadableDatabase */
	private $dbr;

	/**
	 * @param IReadableDatabase $dbr
	 */
	public function __construct( IReadableDatabase $dbr ) {
		$this->dbr = $dbr;
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return 'ipinfo-source-contributions';
	}

	/**
	 * @inheritDoc
	 */
	public function retrieveFromIP( string $ip ): ContributionInfo {
		$hexIP = IPUtils::toHex( $ip );

		$numLocalEdits = $this->dbr->newSelectQueryBuilder()
			->from( 'ip_changes' )
			->where( [
					'ipc_hex' => $hexIP,
				]
			)
			->caller( __METHOD__ )
			->fetchRowCount();

		$oneDayTS = (int)wfTimestamp( TS_UNIX ) - ( 24 * 60 * 60 );
		$numRecentEdits = $this->dbr->newSelectQueryBuilder()
			->from( 'ip_changes' )
			->where( [
					'ipc_hex' => $hexIP,
					'ipc_rev_timestamp > ' . $this->dbr->addQuotes( $this->dbr->timestamp( $oneDayTS ) ),
				]
			)
			->caller( __METHOD__ )
			->fetchRowCount();

		$numDeletedEdits = $this->dbr->newSelectQueryBuilder()
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
