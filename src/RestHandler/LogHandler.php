<?php

namespace MediaWiki\IPInfo\RestHandler;

use DatabaseLogEntry;
use LogEventsList;
use LogPage;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use RequestContext;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\ILoadBalancer;

class LogHandler extends SimpleHandler {

	/** @var InfoManager */
	private $infoManager;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var UserFactory */
	private $userFactory;

	/** @var UserIdentity */
	private $user;

	/**
	 * @param InfoManager $infoManager
	 * @param ILoadBalancer $loadBalancer
	 * @param PermissionManager $permissionManager
	 * @param UserFactory $userFactory
	 * @param UserIdentity $user
	 */
	public function __construct(
		InfoManager $infoManager,
		ILoadBalancer $loadBalancer,
		PermissionManager $permissionManager,
		UserFactory $userFactory,
		UserIdentity $user
	) {
		$this->infoManager = $infoManager;
		$this->loadBalancer = $loadBalancer;
		$this->permissionManager = $permissionManager;
		$this->userFactory = $userFactory;
		$this->user = $user;
	}

	/**
	 * @param InfoManager $infoManager
	 * @param ILoadBalancer $loadBalancer
	 * @param PermissionManager $permissionManager
	 * @param UserFactory $userFactory
	 * @return self
	 */
	public static function factory(
		InfoManager $infoManager,
		ILoadBalancer $loadBalancer,
		PermissionManager $permissionManager,
		UserFactory $userFactory
	) {
		return new self(
			$infoManager,
			$loadBalancer,
			$permissionManager,
			$userFactory,
			// @TODO Replace with something better.
			RequestContext::getMain()->getUser()
		);
	}

	/**
	 * Get IP Info for a Log entry.
	 *
	 * @param int $id
	 * @return Response
	 */
	public function run( int $id ) : Response {
		if ( !$this->permissionManager->userHasRight( $this->user, 'ipinfo' ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-access-denied' ), $this->user->isRegistered() ? 403 : 401 );
		}

		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$entry = DatabaseLogEntry::newFromId( $id, $db );

		if ( !$entry ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-nonexistent' ), 404 );
		}

		$user = $this->userFactory->newFromUserIdentity( $this->user );
		if (
			!LogEventsList::userCanBitfield( $entry->getDeleted(), LogPage::SUPPRESSED_USER, $user )
		) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-denied' ), 403 );
		}

		$performer = $entry->getPerformer()->getName();

		// The target of a log entry may be an IP address. Targets are stored as titles.
		$target = $entry->getTarget()->getText();

		$info = [];
		if ( IPUtils::isValid( $performer ) ) {
			$info[] = $this->infoManager->retrieveFromIP( $performer );
		}
		if ( IPUtils::isValid( $target ) ) {
			$info[] = $this->infoManager->retrieveFromIP( $target );
		}

		if ( count( $info ) === 0 ) {
			// Since the IP address only exists in CheckUser, there is no way to access it.
			// @TODO Allow extensions (like CheckUser) to either pass without a value
			//      (which would result in a 404) or throw a fatal (which could result in a 403).
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-registered' ), 404 );
		}

		// @TODO Figure out a good caching strategy!
		return $this->getResponseFactory()->createJson( [ 'info' => $info ] );
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
