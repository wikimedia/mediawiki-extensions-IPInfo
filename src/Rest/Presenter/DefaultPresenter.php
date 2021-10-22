<?php

namespace MediaWiki\IPInfo\Rest\Presenter;

use MediaWiki\IPInfo\Info\BlockInfo;
use MediaWiki\IPInfo\Info\ContributionInfo;
use MediaWiki\IPInfo\Info\Info;
use MediaWiki\IPInfo\Info\Location;
use Wikimedia\Assert\Assert;

class DefaultPresenter {

	/**
	 * @param array $info The output of MediaWiki\IPInfo\InfoManager::retrieveFromIP()
	 * @return array
	 */
	public function present( array $info ): array {
		Assert::parameterElementType(
			[ Info::class, BlockInfo::class, ContributionInfo::class ],
			$info['data'],
			"info['data']"
		);

		$result = [
			'subject' => $info['subject'],
			'data' => [],
		];

		foreach ( $info['data'] as $source => $info ) {
			$data = [ 'source' => $source ];

			if ( $info instanceof Info ) {
				$data += $this->presentInfo( $info );
			} elseif ( $info instanceof BlockInfo ) {
				$data += $this->presentBlockInfo( $info );
			} elseif ( $info instanceof ContributionInfo ) {
				$data += $this->presentContributionInfo( $info );
			}

			$result['data'][] = $data;
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
			'location' => array_map( static function ( Location $location ) {
				return [
					'id' => $location->getId(),
					'label' => $location->getLabel(),
				];
			}, $info->getLocation() ),
			'isp' => $info->getIsp(),
			'connectionType' => $info->getConnectionType(),
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
