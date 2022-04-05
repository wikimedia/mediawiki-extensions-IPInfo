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
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use RequestContext;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class RevisionHandler extends SimpleHandler {

	use AccessLevelTrait;

	/**
	 * An array of contexts and the data
	 * that should be available in those contexts
	 *
	 * @var array
	 */
	private const VIEWING_CONTEXTS = [
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
	private $infoManager;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var UserFactory */
	private $userFactory;

	/** @var UserIdentity */
	private $user;

	/** @var DefaultPresenter */
	private $presenter;

	/** @var JobQueueGroup */
	private $jobQueueGroup;

	/**
	 * @param InfoManager $infoManager
	 * @param RevisionLookup $revisionLookup
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param UserIdentity $user
	 * @param DefaultPresenter $presenter
	 * @param JobQueueGroup $jobQueueGroup
	 */
	public function __construct(
		InfoManager $infoManager,
		RevisionLookup $revisionLookup,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		UserIdentity $user,
		DefaultPresenter $presenter,
		JobQueueGroup $jobQueueGroup
	) {
		$this->infoManager = $infoManager;
		$this->revisionLookup = $revisionLookup;
		$this->permissionManager = $permissionManager;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userFactory = $userFactory;
		$this->user = $user;
		$this->presenter = $presenter;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * @param InfoManager $infoManager
	 * @param RevisionLookup $revisionLookup
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param JobQueueGroup $jobQueueGroup
	 * @return self
	 */
	public static function factory(
		InfoManager $infoManager,
		RevisionLookup $revisionLookup,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		JobQueueGroup $jobQueueGroup
	) {
		return new self(
			$infoManager,
			$revisionLookup,
			$permissionManager,
			$userOptionsLookup,
			$userFactory,
			// @TODO Replace with something better.
			RequestContext::getMain()->getUser(),
			new DefaultPresenter( $permissionManager ),
			$jobQueueGroup
		);
	}

	/**
	 * Get information about an IP address that authored a revision.
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
			!$this->userOptionsLookup->getOption( $this->user, 'ipinfo-enable' ) ||
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

		$revision = $this->revisionLookup->getRevisionById( $id );

		if ( !$revision ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-nonexistent-revision', [ $id ] ), 404 );
		}

		if ( !$this->permissionManager->userCan( 'read', $user, $revision->getPageAsLinkTarget() ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-revision-permission-denied-revision', [ $id ] ), 403 );
		}

		$author = $revision->getUser( RevisionRecord::FOR_THIS_USER, $user );

		if ( !$author ) {
			// User does not have access to author.
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-revision-no-author' ), 403 );
		}

		if ( $author->isRegistered() ) {
			// Since the IP address only exists in CheckUser, there is no way to access it.
			// @TODO Allow extensions (like CheckUser) to either pass without a value
			//      (which would result in a 404) or throw a fatal (which could result in a 403).
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-revision-registered' ), 404 );
		}

		$info = [
			$this->presenter->present( $this->infoManager->retrieveFromIP( $author->getName() ), $user )
		];

		// Only show data required for the context
		$dataContext = $this->getValidatedParams()['dataContext'];
		foreach ( $info as $index => $set ) {
			if ( isset( $set['data'] ) ) {
				foreach ( $set['data'] as $provider => $dataset ) {
					foreach ( $dataset as $datum => $value ) {
						if ( !in_array( $datum, self::VIEWING_CONTEXTS[$dataContext] ?? [] ) ) {
							unset( $info[$index]['data'][$provider][$datum] );
						}
					}
				}
			}
		}

		$level = $this->highestAccessLevel( $this->permissionManager->getUserPermissions( $user ) );
		$this->jobQueueGroup->push(
			new JobSpecification(
				'ipinfoLogIPInfoAccess',
				[
					'performer' => $user->getName(),
					'ip' => $author->getName(),
					'dataContext' => $dataContext,
					'timestamp' => (int)wfTimestamp(),
					'access_level' => $level
				],
				[],
				null
			)
		);

		$response = $this->getResponseFactory()->createJson( [ 'info' => $info ] );
		$response->setHeader( 'Cache-Control', 'private, max-age=86400' );
		return $response;
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
