<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use DatabaseLogEntry;
use JobQueueGroup;
use LogEventsList;
use LogPage;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use RequestContext;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\Rdbms\ILoadBalancer;

class LogHandler extends IPInfoHandler {

	/** @var ILoadBalancer */
	private $loadBalancer;

	/**
	 * @param InfoManager $infoManager
	 * @param ILoadBalancer $loadBalancer
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param UserIdentity $user
	 * @param DefaultPresenter $presenter
	 * @param JobQueueGroup $jobQueueGroup
	 */
	public function __construct(
		InfoManager $infoManager,
		ILoadBalancer $loadBalancer,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		UserIdentity $user,
		DefaultPresenter $presenter,
		JobQueueGroup $jobQueueGroup
	) {
		parent::__construct(
			$infoManager,
			$permissionManager,
			$userOptionsLookup,
			$userFactory,
			$user,
			$presenter,
			$jobQueueGroup
		);
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @param InfoManager $infoManager
	 * @param ILoadBalancer $loadBalancer
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param JobQueueGroup $jobQueueGroup
	 * @return self
	 */
	public static function factory(
		InfoManager $infoManager,
		ILoadBalancer $loadBalancer,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		JobQueueGroup $jobQueueGroup
	) {
		return new self(
			$infoManager,
			$loadBalancer,
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
	 * @inheritDoc
	 */
	protected function getInfo( int $id ): array {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$entry = DatabaseLogEntry::newFromId( $id, $db );

		if ( !$entry ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-nonexistent' ), 404 );
		}

		$user = $this->userFactory->newFromUserIdentity( $this->user );
		if ( !LogEventsList::userCanViewLogType( $entry->getType(), $user ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-denied' ), 403 );
		}

		// A log entry logs an action performed by a performer, on a target. Either of the
		// performer or target may be an IP address. This returns info about whichever is an
		// IP address, or both, if both are IP addresses.
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
		$showPerformer = IPUtils::isValid( $performer ) && $canAccessPerformer;
		$showTarget = IPUtils::isValid( $target ) && $canAccessTarget;
		if ( $showPerformer ) {
			$info[] = $this->presenter->present( $this->infoManager->retrieveFromIP( $performer ), $this->user );
		}
		if ( $showTarget ) {
			$info[] = $this->presenter->present( $this->infoManager->retrieveFromIP( $target ), $this->user );
		}

		if ( count( $info ) === 0 ) {
			// Since the IP address only exists in CheckUser, there is no way to access it.
			// @TODO Allow extensions (like CheckUser) to either pass without a value
			//      (which would result in a 404) or throw a fatal (which could result in a 403).
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-registered' ), 404 );
		}

		return $info;
	}
}
