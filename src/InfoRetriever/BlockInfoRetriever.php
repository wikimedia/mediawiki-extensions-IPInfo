<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use MediaWiki\Block\BlockManager;
use MediaWiki\Block\CompositeBlock;
use MediaWiki\IPInfo\Info\BlockInfo;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\IDatabase;

class BlockInfoRetriever implements InfoRetriever {
	/** @var BlockManager */
	private $blockManager;

	/** @var Database */
	private $database;

	/**
	 * @param BlockManager $blockManager
	 * @param IDatabase $database
	 */
	public function __construct(
		BlockManager $blockManager,
		IDatabase $database
	) {
		$this->blockManager = $blockManager;
		$this->database = $database;
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return 'ipinfo-source-block';
	}

	/**
	 * @inheritDoc
	 */
	public function retrieveFromIP( string $ip ): BlockInfo {
		// Active block(s)
		$activeBlock = $this->blockManager->getUserBlock(
			UserIdentityValue::newAnonymous( $ip ),
			null,
			true
		);

		if ( $activeBlock ) {
			$numActiveBlocks = $activeBlock instanceof CompositeBlock ? count( $activeBlock->getOriginalBlocks() ) : 1;
		} else {
			$numActiveBlocks = 0;
		}

		// Past block(s)
		//
		// Notes:
		//
		// * The ipblocks table only stores details of active or recently expired blocks. Expired
		//   blocks can be purged from the database at any time by running the purgeExpiredBlocks
		//   maintenance script in MediaWiki Core
		//
		// * All blocks, reblocks, and unblocks have rows in the logging table
		$numBlocks = $this->database->selectRowCount(
			'logging',
			'*',
			[
				'log_type' => 'block',
				'log_action' => 'block',
				'log_namespace' => NS_USER,
				'log_title' => $ip,
			],
			__METHOD__
		);
		$numUnblocks = $this->database->selectRowCount(
			'logging',
			'*',
			[
				'log_type' => 'block',
				'log_action' => 'unblock',
				'log_namespace' => NS_USER,
				'log_title' => $ip,
			],
			__METHOD__
		);

		$numPastBlocks = $numBlocks - $numUnblocks - $numActiveBlocks;

		return new BlockInfo( $numActiveBlocks, $numPastBlocks );
	}
}
