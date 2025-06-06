<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use MediaWiki\IPInfo\Hook\IPInfoHookRunner;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\IPInfoPermissionManager;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Revision\ArchivedRevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\Rdbms\ReadOnlyMode;

/**
 * @internal For use by IPInfo
 */
class ArchivedRevisionHandler extends AbstractRevisionHandler {

	private ArchivedRevisionLookup $archivedRevisionLookup;

	public function __construct(
		InfoManager $infoManager,
		ArchivedRevisionLookup $archivedRevisionLookup,
		PermissionManager $permissionManager,
		UserFactory $userFactory,
		DefaultPresenter $presenter,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback,
		UserIdentityUtils $userIdentityUtils,
		TempUserIPLookup $tempUserIPLookup,
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
		$this->archivedRevisionLookup = $archivedRevisionLookup;
	}

	public static function factory(
		InfoManager $infoManager,
		ArchivedRevisionLookup $archivedRevisionLookup,
		PermissionManager $permissionManager,
		UserFactory $userFactory,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback,
		UserIdentityUtils $userIdentityUtils,
		TempUserIPLookup $tempUserIPLookup,
		IPInfoPermissionManager $ipInfoPermissionManager,
		ReadOnlyMode $readOnlyMode,
		IPInfoHookRunner $ipInfoHookRunner
	): self {
		return new self(
			$infoManager,
			$archivedRevisionLookup,
			$permissionManager,
			$userFactory,
			new DefaultPresenter( $permissionManager ),
			$jobQueueGroup,
			$languageFallback,
			$userIdentityUtils,
			$tempUserIPLookup,
			$ipInfoPermissionManager,
			$readOnlyMode,
			$ipInfoHookRunner
		);
	}

	/** @inheritDoc */
	protected function getRevision( int $id ): ?RevisionRecord {
		if ( !$this->permissionManager->userHasRight( $this->getAuthority()->getUser(), 'deletedhistory' ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-access-denied' ),
				$this->getAuthority()->getUser()->isRegistered() ? 403 : 401
			);
		}

		return $this->archivedRevisionLookup->getArchivedRevisionRecord( null, $id );
	}
}
