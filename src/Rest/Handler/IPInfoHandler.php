<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use ExtensionRegistry;
use JobQueueGroup;
use JobSpecification;
use MediaWiki\IPInfo\AccessLevelTrait;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

abstract class IPInfoHandler extends SimpleHandler {

	use AccessLevelTrait;

	/**
	 * An array of contexts and the data
	 * that should be available in those contexts
	 *
	 * @var array
	 */
	protected const VIEWING_CONTEXTS = [
		'popup' => [
			'country',
			'location',
			'organization',
			'numActiveBlocks',
			'numLocalEdits',
			'numRecentEdits',
		],
		'infobox' => [
			'country',
			'location',
			'connectionType',
			'userType',
			'asn',
			'isp',
			'organization',
			'proxyType',
			'numActiveBlocks',
			'numLocalEdits',
			'numRecentEdits',
		]
	];

	/** @var InfoManager */
	protected $infoManager;

	/** @var PermissionManager */
	protected $permissionManager;

	/** @var UserOptionsLookup */
	protected $userOptionsLookup;

	/** @var UserFactory */
	protected $userFactory;

	/** @var UserIdentity */
	protected $user;

	/** @var DefaultPresenter */
	protected $presenter;

	/** @var JobQueueGroup */
	protected $jobQueueGroup;

	/**
	 * @param InfoManager $infoManager
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param UserIdentity $user
	 * @param DefaultPresenter $presenter
	 * @param JobQueueGroup $jobQueueGroup
	 */
	public function __construct(
		InfoManager $infoManager,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		UserIdentity $user,
		DefaultPresenter $presenter,
		JobQueueGroup $jobQueueGroup
	) {
		$this->infoManager = $infoManager;
		$this->permissionManager = $permissionManager;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userFactory = $userFactory;
		$this->user = $user;
		$this->presenter = $presenter;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * Get information about an IP address (or IP addresses) associated with some entity,
	 * given an ID for the entity.
	 *
	 * Concrete subclasses handle for specific entity types, e.g. revision, log entry, etc.
	 *
	 * @param int $id
	 * @return array[]
	 *  Each array in this array has the following structure:
	 *  - 'subject': IP address
	 *  - 'data': array of arrays, each with the following structure:
	 *    - data source as a string => data array
	 *  TODO: Task to codify this better
	 */
	abstract protected function getInfo( int $id ): array;

	/**
	 * Get information about an IP address, based on the ID of some related entity
	 * (e.g. a revision, log entry, etc.)
	 *
	 * @param int $id
	 * @return Response
	 */
	public function run( int $id ): Response {
		$isBetaFeaturesLoaded = ExtensionRegistry::getInstance()->isLoaded( 'BetaFeatures' );
		// Disallow access to API if BetaFeatures is enabled but the feature is not
		if ( $isBetaFeaturesLoaded &&
			!$this->userOptionsLookup->getOption( $this->user, 'ipinfo-beta-feature-enable' ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-access-denied' ), $this->user->isRegistered() ? 403 : 401 );
		}

		if (
			!$this->permissionManager->userHasRight( $this->user, 'ipinfo' ) ||
			!$this->userOptionsLookup->getOption( $this->user, 'ipinfo-use-agreement' )
		) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-access-denied' ), $this->user->isRegistered() ? 403 : 401 );
		}

		$user = $this->userFactory->newFromUserIdentity( $this->user );

		// Users with blocks on their accounts shouldn't be allowed to view ip info
		if ( $user->getBlock() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-access-denied-blocked-user' ), 403 );
		}

		$info = $this->getInfo( $id );

		// Only show data required for the context
		$dataContext = $this->getValidatedParams()['dataContext'];
		foreach ( $info as $index => $set ) {
			if ( !isset( $set['data'] ) ) {
				continue;
			}
			foreach ( $set['data'] as $provider => $dataset ) {
				foreach ( $dataset as $datum => $value ) {
					if ( !in_array( $datum, self::VIEWING_CONTEXTS[$dataContext] ?? [] ) ) {
						unset( $info[$index]['data'][$provider][$datum] );
					}
				}
			}
		}

		foreach ( $info as $index => $set ) {
			if ( !isset( $set['subject'] ) ) {
				continue;
			}
			$this->logAccess( $this->user, $set['subject'], $dataContext );
		}

		$response = $this->getResponseFactory()->createJson( [ 'info' => $info ] );
		$response->setHeader( 'Cache-Control', 'private, max-age=86400' );
		return $response;
	}

	/**
	 * Log that the IP information was accessed. See also LogIPInfoAccessJob.
	 *
	 * @param UserIdentity $accessingUser
	 * @param string $ip IP address whose information was accessed
	 * @param string $dataContext 'infobox' or 'popup'
	 */
	protected function logAccess( $accessingUser, $ip, $dataContext ) {
		$level = $this->highestAccessLevel(
			$this->permissionManager->getUserPermissions( $accessingUser )
		);
		$this->jobQueueGroup->push(
			new JobSpecification(
				'ipinfoLogIPInfoAccess',
				[
					'performer' => $accessingUser->getName(),
					'ip' => $ip,
					'dataContext' => $dataContext,
					'timestamp' => (int)wfTimestamp(),
					'access_level' => $level,
				],
				[],
				null
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		return [
			'id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'dataContext' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
