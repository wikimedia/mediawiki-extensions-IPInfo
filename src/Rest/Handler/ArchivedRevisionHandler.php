<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use JobQueueGroup;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Revision\ArchivedRevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityUtils;
use Wikimedia\Message\MessageValue;

class ArchivedRevisionHandler extends AbstractRevisionHandler {

	private ArchivedRevisionLookup $archivedRevisionLookup;

	public function __construct(
		InfoManager $infoManager,
		ArchivedRevisionLookup $archivedRevisionLookup,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		DefaultPresenter $presenter,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback,
		UserIdentityUtils $userIdentityUtils,
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
			$extensionRegistry
		);
		$this->archivedRevisionLookup = $archivedRevisionLookup;
	}

	public static function factory(
		InfoManager $infoManager,
		ArchivedRevisionLookup $archivedRevisionLookup,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback,
		UserIdentityUtils $userIdentityUtils,
		?ExtensionRegistry $extensionRegistry = null
	): self {
		return new self(
			$infoManager,
			$archivedRevisionLookup,
			$permissionManager,
			$userOptionsLookup,
			$userFactory,
			new DefaultPresenter( $permissionManager ),
			$jobQueueGroup,
			$languageFallback,
			$userIdentityUtils,
			$extensionRegistry ?? ExtensionRegistry::getInstance()
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
