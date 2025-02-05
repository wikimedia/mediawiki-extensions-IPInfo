<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use DatabaseLogEntry;
use JobQueueGroup;
use LogEventsList;
use LogPage;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityUtils;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\Rdbms\IConnectionProvider;

class LogHandler extends IPInfoHandler {

	private IConnectionProvider $dbProvider;

	private UserIdentityLookup $userIdentityLookup;

	public function __construct(
		InfoManager $infoManager,
		IConnectionProvider $dbProvider,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		DefaultPresenter $presenter,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback,
		UserIdentityUtils $userIdentityUtils,
		UserIdentityLookup $userIdentityLookup,
		TempUserIPLookup $tempUserIPLookup,
		ExtensionRegistry $extensionRegistry
	) {
		parent::__construct(
			$infoManager,
			$permissionManager,
			$userOptionsLookup,
			$userFactory,
			$presenter,
			$jobQueueGroup,
			$languageFallback,
			$userIdentityUtils,
			$tempUserIPLookup,
			$extensionRegistry
		);
		$this->dbProvider = $dbProvider;
		$this->userIdentityLookup = $userIdentityLookup;
	}

	public static function factory(
		InfoManager $infoManager,
		IConnectionProvider $dbProvider,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback,
		UserIdentityUtils $userIdentityUtils,
		UserIdentityLookup $userIdentityLookup,
		TempUserIPLookup $tempUserIPLookup,
		ExtensionRegistry $extensionRegistry
	): self {
		return new self(
			$infoManager,
			$dbProvider,
			$permissionManager,
			$userOptionsLookup,
			$userFactory,
			new DefaultPresenter( $permissionManager ),
			$jobQueueGroup,
			$languageFallback,
			$userIdentityUtils,
			$userIdentityLookup,
			$tempUserIPLookup,
			$extensionRegistry
		);
	}

	/** @inheritDoc */
	protected function getInfo( int $id ): array {
		$dbr = $this->dbProvider->getReplicaDatabase();
		$entry = DatabaseLogEntry::newFromId( $id, $dbr );

		if ( !$entry ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-nonexistent' ),
				404
			);
		}

		if ( !LogEventsList::userCanViewLogType( $entry->getType(), $this->getAuthority() ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-denied' ),
				403
			);
		}

		// A log entry logs an action performed by a performer, on a target. Either the
		// performer, or the target may be an IP address. This returns info about whichever is an
		// IP address, or both, if both are IP addresses.
		$canAccessPerformer = LogEventsList::userCanBitfield( $entry->getDeleted(), LogPage::DELETED_USER,
			$this->getAuthority() );
		$canAccessTarget = LogEventsList::userCanBitfield( $entry->getDeleted(), LogPage::DELETED_ACTION,
			$this->getAuthority() );

		// If the user cannot access the performer, nor the target, throw an error since there won't
		// be anything to respond with.
		if ( !$canAccessPerformer && !$canAccessTarget ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-denied' ),
				403
			);
		}

		$performer = $entry->getPerformerIdentity();
		$target = $this->userIdentityLookup->getUserIdentityByName( $entry->getTarget()->getText() );

		$info = [];
		$showPerformer = $canAccessPerformer && $this->isAnonymousOrTempUser( $performer );
		$showTarget = $canAccessTarget && $this->isAnonymousOrTempUser( $target );
		if ( $showPerformer ) {
			$performerAddress = $this->tempUserIPLookup->getAddressForLogEntry( $entry );
			$info[] = $this->presenter->present(
				$this->infoManager->retrieveFor( $performer, $performerAddress ),
				$this->getAuthority()->getUser()
			);
		}
		if ( $showTarget ) {
			// $target is implicitly null-checked via isAnonymousOrTempUser()
			'@phan-var UserIdentity $target';

			$targetAddress = $this->tempUserIPLookup->getMostRecentAddress( $target );

			$info[] = $this->presenter->present( $this->infoManager->retrieveFor( $target, $targetAddress ),
			$this->getAuthority()->getUser() );
		}

		if ( count( $info ) === 0 ) {
			// Since the IP address only exists in CheckUser, there is no way to access it.
			// @TODO Allow extensions (like CheckUser) to either pass without a value
			//      (which would result in a 404) or throw a fatal (which could result in a 403).
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-registered' ),
				404
			);
		}

		return $info;
	}

	/**
	 * Determine whether the given user is an anonymous or temporary user account.
	 *
	 * @param UserIdentity|null $user The user to check. May be `null`.
	 * @return bool Whether the given user is an anonymous or temporary user.
	 */
	private function isAnonymousOrTempUser( ?UserIdentity $user ): bool {
		if ( $user === null ) {
			return false;
		}

		return $this->userIdentityUtils->isTemp( $user ) || IPUtils::isValid( $user->getName() );
	}
}
