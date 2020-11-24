<?php

namespace MediaWiki\IPInfo\RestHandler;

use MediaWiki\IPInfo\InfoManager;
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

	/**
	 * @param InfoManager $infoManager
	 * @param RevisionLookup $revisionLookup
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param UserIdentity $user
	 */
	public function __construct(
		InfoManager $infoManager,
		RevisionLookup $revisionLookup,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		UserIdentity $user
	) {
		$this->infoManager = $infoManager;
		$this->revisionLookup = $revisionLookup;
		$this->permissionManager = $permissionManager;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userFactory = $userFactory;
		$this->user = $user;
	}

	/**
	 * @param InfoManager $infoManager
	 * @param RevisionLookup $revisionLookup
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @return self
	 */
	public static function factory(
		InfoManager $infoManager,
		RevisionLookup $revisionLookup,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory
	) {
		return new self(
			$infoManager,
			$revisionLookup,
			$permissionManager,
			$userOptionsLookup,
			$userFactory,
			// @TODO Replace with something better.
			RequestContext::getMain()->getUser()
		);
	}

	/**
	 * Get IP Info for a Revision.
	 *
	 * @param int $id
	 * @return Response
	 */
	public function run( int $id ) : Response {
		if (
			!$this->permissionManager->userHasRight( $this->user, 'ipinfo' ) ||
			!$this->userOptionsLookup->getOption( $this->user, 'ipinfo-enable' )
		) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-access-denied' ), $this->user->isRegistered() ? 403 : 401 );
		}

		$revision = $this->revisionLookup->getRevisionById( $id );

		if ( !$revision ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-nonexistent-revision', [ $id ] ), 404 );
		}

		$user = $this->userFactory->newFromUserIdentity( $this->user );
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

		$info = [ $this->infoManager->retrieveFromIP( $author->getName() ) ];

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
		];
	}
}
