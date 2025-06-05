<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use MediaWiki\IPInfo\AnonymousUserIPLookup;
use MediaWiki\IPInfo\Hook\IPInfoHookRunner;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\IPInfoPermissionManager;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\Logging\LogEventsList;
use MediaWiki\Logging\LogPage;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityUtils;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\ReadOnlyMode;

/**
 * @internal For use by IPInfo
 */
class LogHandler extends IPInfoHandler {

	private IConnectionProvider $dbProvider;
	private UserIdentityLookup $userIdentityLookup;
	private AnonymousUserIPLookup $anonymousUserIPLookup;

	public function __construct(
		InfoManager $infoManager,
		IConnectionProvider $dbProvider,
		PermissionManager $permissionManager,
		UserFactory $userFactory,
		DefaultPresenter $presenter,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback,
		UserIdentityUtils $userIdentityUtils,
		UserIdentityLookup $userIdentityLookup,
		TempUserIPLookup $tempUserIPLookup,
		AnonymousUserIPLookup $anonymousUserIPLookup,
		IPInfoPermissionManager $ipInfoPermissionManager,
		ReadOnlyMode $readOnlyMode,
		IPInfoHookRunner $ipInfoHookRunner
	) {
		parent::__construct(
			$infoManager,
			$permissionManager,
			$userFactory,
			$presenter,
			$jobQueueGroup,
			$languageFallback,
			$userIdentityUtils,
			$tempUserIPLookup,
			$ipInfoPermissionManager,
			$readOnlyMode,
			$ipInfoHookRunner
		);
		$this->dbProvider = $dbProvider;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->anonymousUserIPLookup = $anonymousUserIPLookup;
	}

	public static function factory(
		InfoManager $infoManager,
		IConnectionProvider $dbProvider,
		PermissionManager $permissionManager,
		UserFactory $userFactory,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback,
		UserIdentityUtils $userIdentityUtils,
		UserIdentityLookup $userIdentityLookup,
		TempUserIPLookup $tempUserIPLookup,
		AnonymousUserIPLookup $anonymousUserIPLookup,
		IPInfoPermissionManager $ipInfoPermissionManager,
		ReadOnlyMode $readOnlyMode,
		IPInfoHookRunner $ipInfoHookRunner
	): self {
		return new self(
			$infoManager,
			$dbProvider,
			$permissionManager,
			$userFactory,
			new DefaultPresenter( $permissionManager ),
			$jobQueueGroup,
			$languageFallback,
			$userIdentityUtils,
			$userIdentityLookup,
			$tempUserIPLookup,
			$anonymousUserIPLookup,
			$ipInfoPermissionManager,
			$readOnlyMode,
			$ipInfoHookRunner
		);
	}

	/** @inheritDoc */
	protected function getInfo( $id ): array {
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
		$lookups = [];
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
		$showPerformer = $canAccessPerformer && $this->isAnonymousOrTempUser( $performer );
		if ( $showPerformer ) {
			$performerAddress = $this->tempUserIPLookup->getAddressForLogEntry( $entry );
			$lookups[] = [ $performer, $performerAddress ];
		}

		// Save the target name independently of the returned UserIdentity, as some actors (IPs only known to
		// AbuseFilter/CheckUser databases - see the AnonymousUserIPLookup class) should be known to lookup but
		// will not have an associated UserIdentity. This allows lookup of these users as a fallback later.
		$targetName = $entry->getTarget()->getText();
		$target = $this->userIdentityLookup->getUserIdentityByName( $targetName );
		$showTarget = $canAccessTarget && $this->isAnonymousOrTempUser( $target );
		if ( $showTarget ) {
			// $target is implicitly null-checked via isAnonymousOrTempUser()
			'@phan-var UserIdentity $target';

			$targetAddress = $this->tempUserIPLookup->getMostRecentAddress( $target );
			$lookups[] = [ $target, $targetAddress ];
		}

		// Throw if all accessible lookups are registered accounts as that account type is not supported.
		if (
			( !$canAccessPerformer && $target && !$this->isAnonymousOrTempUser( $target ) ) ||
			( !$canAccessTarget && !$this->isAnonymousOrTempUser( $performer ) ) ||
			(
				$canAccessPerformer && $canAccessTarget && $target &&
				!$this->isAnonymousOrTempUser( $target ) && !$this->isAnonymousOrTempUser( $performer )
			)
		) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-log-registered' ),
				404
			);
		}

		// The target may be an IP with a blocked action and only be known to CheckUser or AbuseFilter.
		// In those cases, it won't return a UserIdentity and will fail the $showTarget check although it
		// is still eligible to be looked up. Check for those cases here.
		// There is no need to do a similar check for the performer as that is guaranteed to have returned a UserIdentity.
		$targetName = IPUtils::sanitizeIP( $targetName );
		if (
			$canAccessTarget &&
			!$showTarget &&
			IPUtils::isValid( $targetName ) &&
			$this->anonymousUserIPLookup->checkIPIsKnown( $targetName ) ) {
				$lookups[] = [ $targetName, $targetName ];
		}

		// All possible lookup subjects have been gathered, do the actual lookup.
		$info = [];
		foreach ( $lookups as $lookup ) {
			$info[] = $this->presenter->present(
				$this->infoManager->retrieveFor( $lookup[0], $lookup[1] ),
				$this->getAuthority()->getUser()
			);
		}

		if ( count( $info ) ) {
			return $info;
		}

		// If no info was found, it doesn't exist or has expired
		throw new LocalizedHttpException(
			new MessageValue( 'ipinfo-rest-log-default' ),
			404
		);
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

	/** @inheritDoc */
	public function getParamSettings() {
		$params = parent::getParamSettings();
		$params[ 'id' ] = [
			self::PARAM_SOURCE => 'path',
			ParamValidator::PARAM_TYPE => 'integer',
			ParamValidator::PARAM_REQUIRED => true,
		];
		return $params;
	}
}
