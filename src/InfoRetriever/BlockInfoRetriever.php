<?php

namespace MediaWiki\IPInfo\InfoRetriever;

use MediaWiki\Block\Block;
use MediaWiki\Block\BlockManager;
use MediaWiki\IPInfo\Info\BlockInfo;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;

class BlockInfoRetriever extends BaseInfoRetriever {
	public const NAME = 'ipinfo-source-block';

	private BlockManager $blockManager;
	private UserIdentityUtils $userIdentityUtils;

	public function __construct(
		BlockManager $blockManager,
		UserIdentityUtils $userIdentityUtils
	) {
		$this->blockManager = $blockManager;
		$this->userIdentityUtils = $userIdentityUtils;
	}

	/** @inheritDoc */
	public function getName(): string {
		return self::NAME;
	}

	/** @inheritDoc */
	public function retrieveFor( UserIdentity $user, ?string $ip ): BlockInfo {
		// Active block(s)
		if ( $this->userIdentityUtils->isTemp( $user ) ) {
			$activeBlock = $this->blockManager->getBlock( $user, null );
		} else {
			$activeBlock = $this->blockManager->getIPBlock( $user->getName(), true );
		}

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
