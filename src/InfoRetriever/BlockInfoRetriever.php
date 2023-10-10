<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use MediaWiki\Block\Block;
use MediaWiki\Block\BlockManager;
use MediaWiki\IPInfo\Info\BlockInfo;

class BlockInfoRetriever implements InfoRetriever {
	/** @var BlockManager */
	private $blockManager;

	/**
	 * @param BlockManager $blockManager
	 */
	public function __construct( BlockManager $blockManager ) {
		$this->blockManager = $blockManager;
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
		$activeBlock = $this->blockManager->getIPBlock( $ip, true );

		if ( $activeBlock ) {
			// SECURITY: do not include autoblocks in the number of blocks shown to the user, T310763
			$allBlocks = $activeBlock->toArray();
			$nonAutoBlocks = array_filter(
				$allBlocks,
				static function ( $block ) {
					return $block->getType() !== Block::TYPE_AUTO;
				}
			);
			$numActiveBlocks = count( $nonAutoBlocks );
		} else {
			$numActiveBlocks = 0;
		}

		// Past block(s)
		//
		// TODO
		//
		// Notes:
		//
		// * The ipblocks table only stores details of active or recently expired blocks. Expired
		//   blocks can be purged from the database at any time by running the
		//   maintenance/purgeExpiredBlocks.php script in MediaWiki Core
		//
		// * All blocks, reblocks, and unblocks have rows in the logging table. However, the
		//   table does not support querying by IP address range like the ipblocks table does.

		return new BlockInfo( $numActiveBlocks );
	}
}
