<?php

namespace MediaWiki\IPInfo\Rest\Presenter;

use MediaWiki\IPInfo\Info\BlockInfo;
use MediaWiki\IPInfo\Info\ContributionInfo;
use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\Info\Location;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserIdentity;
use Wikimedia\Assert\Assert;

class DefaultPresenter {
	/** @var PermissionManager */
	private $permissionManager;

	/**
	 * An ordered list of the viewing privileges of each level
	 * They are ordered from lowest to highest access and each
	 * describe themselves independently of one another
	 *
	 * @var array
	 */
	private const VIEWING_RIGHTS = [
		'ipinfo-view-basic' => [
			'country',
			'connectionType',
			'userType',
			'proxyType',
			'numActiveBlocks',
			'numLocalEdits',
			'numRecentEdits',
		],
		'ipinfo-view-full' => [
			'country',
			'location',
			'connectionType',
			'userType',
			'isp',
			'organization',
			'proxyType',
			'numActiveBlocks',
			'numLocalEdits',
			'numRecentEdits',
		]
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
	 * @param array $info The output of MediaWiki\IPInfo\InfoManager::retrieveFromIP()
	 * @param UserIdentity $user User performing the request
	 * @return array
	 */
	public function present( array $info, UserIdentity $user ): array {
		Assert::parameterElementType(
			[ Info::class, BlockInfo::class, ContributionInfo::class ],
			$info['data'],
			"info['data']"
		);

		$result = [
			'subject' => $info['subject'],
			'data' => [],
		];

		// Get the highest access list of properties user has permissions for
		$viewableProperties = [];
		$userPermissions = $this->permissionManager->getUserPermissions( $user );
		foreach ( self::VIEWING_RIGHTS as $level => $properties ) {
			if ( in_array( $level, $userPermissions ) ) {
				$viewableProperties = $properties;
			}
		}

		foreach ( $info['data'] as $source => $info ) {
			$data = [];

			if ( $info instanceof Info ) {
				$data += $this->presentInfo( $info );
			} elseif ( $info instanceof BlockInfo ) {
				$data += $this->presentBlockInfo( $info );
			} elseif ( $info instanceof ContributionInfo ) {
				$data += $this->presentContributionInfo( $info );
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

		return [
			'coordinates' => $coordinates ? [
				'latitude' => $coordinates->getLatitude(),
				'longitude' => $coordinates->getLongitude(),
			] : null,
			'asn' => $info->getAsn(),
			'organization' => $info->getOrganization(),
			'country' => array_map( static function ( Location $location ) {
				return [
					'id' => $location->getId(),
					'label' => $location->getLabel(),
				];
			}, $info->getCountry() ),
			'location' => array_map( static function ( Location $location ) {
				return [
					'id' => $location->getId(),
					'label' => $location->getLabel(),
				];
			}, $info->getLocation() ),
			'isp' => $info->getIsp(),
			'connectionType' => $info->getConnectionType(),
			'userType' => $info->getUserType(),
			'proxyType' => $proxyType ? [
				'isAnonymous' => $proxyType->isAnonymous(),
				'isAnonymousVpn' => $proxyType->isAnonymousVpn(),
				'isPublicProxy' => $proxyType->isPublicProxy(),
				'isResidentialProxy' => $proxyType->isResidentialProxy(),
				'isLegitimateProxy' => $proxyType->isLegitimateProxy(),
				'isTorExitNode' => $proxyType->isTorExitNode(),

			] : null,
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
	 * @return array<string,int>
	 */
	private function presentContributionInfo( ContributionInfo $info ): array {
		return [
			'numLocalEdits' => $info->getNumLocalEdits(),
			'numRecentEdits' => $info->getNumRecentEdits(),
		];
	}
}
