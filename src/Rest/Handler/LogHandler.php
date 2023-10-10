<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use DatabaseLogEntry;
use JobQueueGroup;
use LogEventsList;
use LogPage;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserOptionsLookup;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\Rdbms\IConnectionProvider;

class LogHandler extends IPInfoHandler {

	/** @var IConnectionProvider */
	private $dbProvider;

	/**
	 * @param InfoManager $infoManager
	 * @param IConnectionProvider $dbProvider
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param DefaultPresenter $presenter
	 * @param JobQueueGroup $jobQueueGroup
	 * @param LanguageFallback $languageFallback
	 */
	public function __construct(
		InfoManager $infoManager,
		IConnectionProvider $dbProvider,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		DefaultPresenter $presenter,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback
	) {
		parent::__construct(
			$infoManager,
			$permissionManager,
			$userOptionsLookup,
			$userFactory,
			$presenter,
			$jobQueueGroup,
			$languageFallback,
		);
		$this->dbProvider = $dbProvider;
	}

	/**
	 * @param InfoManager $infoManager
	 * @param IConnectionProvider $dbProvider
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param JobQueueGroup $jobQueueGroup
	 * @param LanguageFallback $languageFallback
	 * @return self
	 */
	public static function factory(
		InfoManager $infoManager,
		IConnectionProvider $dbProvider,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback
	) {
		return new self(
			$infoManager,
			$dbProvider,
			$permissionManager,
			$userOptionsLookup,
			$userFactory,
			new DefaultPresenter( $permissionManager ),
			$jobQueueGroup,
			$languageFallback
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getInfo( int $id ): array {
		$dbr = $this->dbProvider->getReplicaDatabase();
		$entry = DatabaseLogEntry::newFromId( $id, $dbr );

		if ( !$entry ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-nonexistent' ), 404 );
		}

		if ( !LogEventsList::userCanViewLogType( $entry->getType(), $this->getAuthority() ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-denied' ), 403 );
		}

		// A log entry logs an action performed by a performer, on a target. Either of the
		// performer or target may be an IP address. This returns info about whichever is an
		// IP address, or both, if both are IP addresses.
		$canAccessPerformer = LogEventsList::userCanBitfield( $entry->getDeleted(), LogPage::DELETED_USER,
			$this->getAuthority() );
		$canAccessTarget = LogEventsList::userCanBitfield( $entry->getDeleted(), LogPage::DELETED_ACTION,
			$this->getAuthority() );

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
			$info[] = $this->presenter->present( $this->infoManager->retrieveFromIP( $performer ),
				$this->getAuthority()->getUser() );
		}
		if ( $showTarget ) {
			$info[] = $this->presenter->present( $this->infoManager->retrieveFromIP( $target ),
			$this->getAuthority()->getUser() );
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
