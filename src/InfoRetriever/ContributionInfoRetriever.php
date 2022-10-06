<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use MediaWiki\IPInfo\Info\ContributionInfo;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IDatabase;

class ContributionInfoRetriever implements InfoRetriever {
	/** @var IDatabase */
	private $database;

	/**
	 * @param IDatabase $database
	 */
	public function __construct(
		IDatabase $database
	) {
		$this->database = $database;
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
		$numLocalEdits = $this->database->selectRowCount(
			'ip_changes',
			'*',
			[
				'ipc_hex' => $hexIP,
			],
			__METHOD__
		);
		$oneDayTS = (int)wfTimestamp( TS_UNIX ) - ( 24 * 60 * 60 );
		$numRecentEdits = $this->database->selectRowCount(
			'ip_changes',
			'*',
			[
				'ipc_hex' => $hexIP,
				'ipc_rev_timestamp > ' . $this->database->addQuotes( $this->database->timestamp( $oneDayTS ) ),
			],
			__METHOD__
		);

		return new ContributionInfo( $numLocalEdits, $numRecentEdits );
	}
}