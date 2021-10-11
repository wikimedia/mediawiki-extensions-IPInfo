<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use DatabaseLogEntry;
use LogEventsList;
use LogPage;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
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

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var UserFactory */
	private $userFactory;

	/** @var UserIdentity */
	private $user;

	/** @var DefaultPresenter */
	private $presenter;

	/**
	 * @param InfoManager $infoManager
	 * @param ILoadBalancer $loadBalancer
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param UserIdentity $user
	 * @param DefaultPresenter $presenter
	 */
	public function __construct(
		InfoManager $infoManager,
		ILoadBalancer $loadBalancer,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		UserIdentity $user,
		DefaultPresenter $presenter
	) {
		$this->infoManager = $infoManager;
		$this->loadBalancer = $loadBalancer;
		$this->permissionManager = $permissionManager;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userFactory = $userFactory;
		$this->user = $user;
		$this->presenter = $presenter;
	}

	/**
	 * @param InfoManager $infoManager
	 * @param ILoadBalancer $loadBalancer
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @return self
	 */
	public static function factory(
		InfoManager $infoManager,
		ILoadBalancer $loadBalancer,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory
	) {
		return new self(
			$infoManager,
			$loadBalancer,
			$permissionManager,
			$userOptionsLookup,
			$userFactory,
			// @TODO Replace with something better.
			RequestContext::getMain()->getUser(),
			new DefaultPresenter()
		);
	}

	/**
	 * Get IP Info for a Log entry.
	 *
	 * @param int $id
	 * @return Response
	 */
	public function run( int $id ): Response {
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

		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$entry = DatabaseLogEntry::newFromId( $id, $db );

		if ( !$entry ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-nonexistent' ), 404 );
		}

		if ( !LogEventsList::userCanViewLogType( $entry->getType(), $user ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-denied' ), 403 );
		}

		$canAccessPerformer = LogEventsList::userCanBitfield( $entry->getDeleted(), LogPage::DELETED_USER, $user );
		$canAccessTarget = LogEventsList::userCanBitfield( $entry->getDeleted(), LogPage::DELETED_ACTION, $user );

		// If the user cannot access the performer, nor the target, throw an error since there wont
		// be anything to respond with.
		if ( !$canAccessPerformer && !$canAccessTarget ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-denied' ), 403 );
		}

		$performer = $entry->getPerformerIdentity()->getName();

		// The target of a log entry may be an IP address. Targets are stored as titles.
		$target = $entry->getTarget()->getText();

		$info = [];
		if ( IPUtils::isValid( $performer ) && $canAccessPerformer ) {
			$info[] = $this->presenter->present( $this->infoManager->retrieveFromIP( $performer ) );
		}
		if ( IPUtils::isValid( $target ) && $canAccessTarget ) {
			$info[] = $this->presenter->present( $this->infoManager->retrieveFromIP( $target ) );
		}

		if ( count( $info ) === 0 ) {
			// Since the IP address only exists in CheckUser, there is no way to access it.
			// @TODO Allow extensions (like CheckUser) to either pass without a value
			//      (which would result in a 404) or throw a fatal (which could result in a 403).
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-registered' ), 404 );
		}

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
