<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use MediaWiki\IPInfo\Info\ContributionInfo;
use MediaWiki\User\ActorNormalization;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IConnectionProvider;

class ContributionInfoRetriever implements InfoRetriever {
	private IConnectionProvider $dbProvider;
	private ActorNormalization $actorNormalization;

	public function __construct(
		IConnectionProvider $dbProvider,
		ActorNormalization $actorNormalization
	) {
		$this->dbProvider = $dbProvider;
		$this->actorNormalization = $actorNormalization;
	}

	/** @inheritDoc */
	public function getName(): string {
		return 'ipinfo-source-contributions';
	}

	/** @inheritDoc */
	public function retrieveFor( UserIdentity $user, ?string $ip ): ContributionInfo {
		$dbr = $this->dbProvider->getReplicaDatabase();
		$actorId = $this->actorNormalization->findActorId( $user, $dbr );

		if ( $actorId === null ) {
			return new ContributionInfo();
		}

		$numLocalEdits = $dbr->newSelectQueryBuilder()
			->from( 'revision' )
			->where( [
				'rev_actor' => $actorId,
			] )
			->caller( __METHOD__ )
			->fetchRowCount();

		$oneDayTS = (int)wfTimestamp( TS_UNIX ) - ( 24 * 60 * 60 );
		$numRecentEdits = $dbr->newSelectQueryBuilder()
			->from( 'revision' )
			->where( [
				'rev_actor' => $actorId,
				$dbr->expr( 'rev_timestamp', '>', $dbr->timestamp( $oneDayTS ) ),
			] )
			->caller( __METHOD__ )
			->fetchRowCount();

		$numDeletedEdits = $dbr->newSelectQueryBuilder()
			->from( 'archive' )
			->where( [
				'ar_actor' => $actorId,
			] )
			->caller( __METHOD__ )
			->fetchRowCount();

		return new ContributionInfo( $numLocalEdits, $numRecentEdits, $numDeletedEdits );
	}
}
