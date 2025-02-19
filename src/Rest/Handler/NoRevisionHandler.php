<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use JobQueueGroup;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class NoRevisionHandler extends IPInfoHandler {

	public static function factory(
		InfoManager $infoManager,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback,
		UserIdentityUtils $userIdentityUtils,
		TempUserIPLookup $tempUserIPLookup,
		ExtensionRegistry $extensionRegistry
	): self {
		return new self(
			$infoManager,
			$permissionManager,
			$userOptionsLookup,
			$userFactory,
			new DefaultPresenter( $permissionManager ),
			$jobQueueGroup,
			$languageFallback,
			$userIdentityUtils,
			$tempUserIPLookup,
			$extensionRegistry
		);
	}

	/** @inheritDoc */
	protected function getInfo( $id ): array {
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
