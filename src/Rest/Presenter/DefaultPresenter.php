<?php

namespace MediaWiki\IPInfo\Rest\Presenter;

use MediaWiki\IPInfo\AccessLevelTrait;
use MediaWiki\IPInfo\Info\BlockInfo;
use MediaWiki\IPInfo\Info\ContributionInfo;
use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\Info\IPCountInfo;
use MediaWiki\IPInfo\Info\IPoidInfo;
use MediaWiki\IPInfo\Info\Location;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserIdentity;
use Wikimedia\Assert\Assert;

class DefaultPresenter {
	use AccessLevelTrait;

	private PermissionManager $permissionManager;

	public const IPINFO_VIEW_BASIC_RIGHT = 'ipinfo-view-basic';
	public const IPINFO_VIEW_FULL_RIGHT = 'ipinfo-view-full';

	private const IPINFO_VIEW_BASIC = [
		'connectionType',
		'countryNames',
		'numActiveBlocks',
		'numLocalEdits',
		'numRecentEdits',
		'proxyType',
		'userType',
	];

	private const IPINFO_VIEW_FULL = [
		'asn',
		'behaviors',
		'connectionType',
		'connectionTypes',
		'countryNames',
		'isp',
		'location',
		'numActiveBlocks',
		'numDeletedEdits',
		'numLocalEdits',
		'numRecentEdits',
		'numUsersOnThisIP',
		'organization',
		'proxies',
		'proxyType',
		'risks',
		'tunnelOperators',
		'userType',
	];

	/**
	 * The viewing privileges of each level.
	 *
	 * Each describes themselves independently of one another.
	 */
	private const VIEWING_RIGHTS = [
		self::IPINFO_VIEW_BASIC_RIGHT => self::IPINFO_VIEW_BASIC,
		self::IPINFO_VIEW_FULL_RIGHT => self::IPINFO_VIEW_FULL,
	];

	/**
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		PermissionManager $permissionManager
	) {
		$this->permissionManager = $permissionManager;
	}

	/**
	 * @param array $info The output of {@link MediaWiki\IPInfo\InfoManager::retrieveFor()}
	 * @param UserIdentity $user User performing the request
	 * @return array
	 */
	public function present( array $info, UserIdentity $user ): array {
		Assert::parameterElementType(
			[ Info::class, IPCountInfo::class, IPoidInfo::class, BlockInfo::class, ContributionInfo::class ],
			$info['data'],
			"info['data']"
		);

		$result = [
			'subject' => $info['subject'],
			'data' => [],
		];

		// Get the highest access list of properties user has permissions for
		$level = $this->highestAccessLevel( $this->permissionManager->getUserPermissions( $user ) );
		$viewableProperties = $level ? self::VIEWING_RIGHTS[$level] : [];

		foreach ( $info['data'] as $source => $itemInfo ) {
			$data = [];

			if ( $itemInfo instanceof Info ) {
				$data += $this->presentInfo( $itemInfo );
			} elseif ( $itemInfo instanceof IPCountInfo ) {
				// FIXME: This is just a temporary bandaid patch for T371966
				continue;
			} elseif ( $itemInfo instanceof IPoidInfo ) {
				$data += $this->presentIPoidInfo( $itemInfo );
			} elseif ( $itemInfo instanceof BlockInfo ) {
				$data += $this->presentBlockInfo( $itemInfo );
			} elseif ( $itemInfo instanceof ContributionInfo ) {
				$data += $this->presentContributionInfo( $itemInfo, $user );
			}

			// Unset all properties the user doesn't have access to before writing to $result
			foreach ( $data as $datum => $value ) {
				if ( !in_array( $datum, $viewableProperties ) ) {
					unset( $data[$datum] );
				}
			}

			$result['data'][$source] = $data;
		}

		return $result;
	}

	/**
	 * Converts an instance of `\MediaWiki\IPInfo\Info\Info` to an array.
	 *
	 * @param Info $info
	 * @return array<string,mixed>
	 */
	private function presentInfo( Info $info ): array {
		$coordinates = $info->getCoordinates();
		$proxyType = $info->getProxyType();
		$location = $info->getLocation();

		return [
			'coordinates' => $coordinates ? [
				'latitude' => $coordinates->getLatitude(),
				'longitude' => $coordinates->getLongitude(),
			] : null,
			'asn' => $info->getAsn(),
			'organization' => $info->getOrganization(),
			'countryNames' => $info->getCountryNames(),
			'location' => $location ? array_map( static function ( Location $location ) {
				return [
					'id' => $location->getId(),
					'label' => $location->getLabel(),
				];
			}, $location ) : null,
			'isp' => $info->getIsp(),
			'connectionType' => $info->getConnectionType(),
			'userType' => $info->getUserType(),
			'proxyType' => $proxyType ? [
				'isAnonymousVpn' => $proxyType->isAnonymousVpn(),
				'isPublicProxy' => $proxyType->isPublicProxy(),
				'isResidentialProxy' => $proxyType->isResidentialProxy(),
				'isLegitimateProxy' => $proxyType->isLegitimateProxy(),
				'isTorExitNode' => $proxyType->isTorExitNode(),
				'isHostingProvider' => $proxyType->isHostingProvider(),

			] : null,
		];
	}

	/**
	 * @param IPoidInfo $info
	 * @return array<string,mixed>
	 */
	private function presentIPoidInfo( IPoidInfo $info ): array {
		return [
			'behaviors' => $info->getBehaviors(),
			'risks' => $info->getRisks(),
			'connectionTypes' => $info->getConnectionTypes(),
			'tunnelOperators' => $info->getTunnelOperators(),
			'proxies' => $info->getProxies(),
			'numUsersOnThisIP' => $info->getNumUsersOnThisIP(),
		];
	}

	/**
	 * @param BlockInfo $info
	 * @return array<string,int>
	 */
	private function presentBlockInfo( BlockInfo $info ): array {
		return [
			'numActiveBlocks' => $info->getNumActiveBlocks(),
		];
	}

	/**
	 * @param ContributionInfo $info
	 * @param UserIdentity $user
	 * @return array<string,int>
	 */
	private function presentContributionInfo( ContributionInfo $info, UserIdentity $user ): array {
		$contributionInfo = [
			'numLocalEdits' => $info->getNumLocalEdits(),
			'numRecentEdits' => $info->getNumRecentEdits(),
		];
		if ( $this->permissionManager->userHasRight(
			 $user, 'deletedhistory' ) ) {
			$contributionInfo['numDeletedEdits'] = $info->getNumDeletedEdits();
		}
		return $contributionInfo;
	}
}
