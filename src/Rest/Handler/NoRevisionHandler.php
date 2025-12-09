<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\IPInfo\AnonymousUserIPLookup;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\IPInfoPermissionManager;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityUtils;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\ReadOnlyMode;

/**
 * @internal For use by IPInfo
 */
class NoRevisionHandler extends IPInfoHandler {

	private AnonymousUserIPLookup $anonymousUserIPLookup;

	public function __construct(
		InfoManager $infoManager,
		PermissionManager $permissionManager,
		UserFactory $userFactory,
		DefaultPresenter $presenter,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback,
		UserIdentityUtils $userIdentityUtils,
		TempUserIPLookup $tempUserIPLookup,
		IPInfoPermissionManager $ipInfoPermissionManager,
		AnonymousUserIPLookup $anonymousUserIPLookup,
		ReadOnlyMode $readOnlyMode,
		HookContainer $hookContainer
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
			$hookContainer
		);
		$this->anonymousUserIPLookup = $anonymousUserIPLookup;
	}

	public static function factory(
		InfoManager $infoManager,
		PermissionManager $permissionManager,
		UserFactory $userFactory,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback,
		UserIdentityUtils $userIdentityUtils,
		TempUserIPLookup $tempUserIPLookup,
		IPInfoPermissionManager $ipInfoPermissionManager,
		ReadOnlyMode $readOnlyMode,
		AnonymousUserIPLookup $anonymousUserIPLookup,
		HookContainer $hookContainer
	): self {
		return new self(
			$infoManager,
			$permissionManager,
			$userFactory,
			new DefaultPresenter( $permissionManager ),
			$jobQueueGroup,
			$languageFallback,
			$userIdentityUtils,
			$tempUserIPLookup,
			$ipInfoPermissionManager,
			$anonymousUserIPLookup,
			$readOnlyMode,
			$hookContainer
		);
	}

	/** @inheritDoc */
	protected function getInfo( $id ): array {
		// Check if the user is an IP and if so, only retrieve information for it
		// if it's known to the wiki (CU/AF logs)
		if ( IPUtils::isValid( $id ) ) {
			if ( $this->anonymousUserIPLookup->checkIPIsKnown( $id ) ) {
				return [
					$this->presenter->present(
						$this->infoManager->retrieveFor( $id, $id ),
						$this->getAuthority()->getUser()
					)
				];
			} else {
				return [];
			}
		}

		$user = $this->userFactory->newFromName( $id );
		if ( !$user ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-nonexistent-user' ),
				404
			);
		}

		// Pretend like the target user doesn't exist if the requestor doesn't have permission to see them
		if (
			$user->isHidden() &&
			!$this->permissionManager->userHasRight( $this->getAuthority()->getUser(), 'hideuser' )
		) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-nonexistent-user' ),
				404
			);
		}

		// Return early if the target is a named account as this endpoint doesn't support those lookups
		if ( $user->isNamed() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-invalid-user' ),
				403
			);
		}

		$ip = $this->tempUserIPLookup->getMostRecentAddress( $user );
		if ( $ip ) {
			return [
				$this->presenter->present(
					$this->infoManager->retrieveFor( $id, $ip ),
					$this->getAuthority()->getUser()
				)
			];
		}

		return [];
	}

	/** @inheritDoc */
	public function getParamSettings() {
		$params = parent::getParamSettings();
		$params[ 'username' ] = [
			self::PARAM_SOURCE => 'path',
			ParamValidator::PARAM_TYPE => 'string',
			ParamValidator::PARAM_REQUIRED => true,
		];
		return $params;
	}
}
