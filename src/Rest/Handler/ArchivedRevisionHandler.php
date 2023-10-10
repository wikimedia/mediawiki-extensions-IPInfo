<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use JobQueueGroup;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Revision\ArchivedRevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserOptionsLookup;
use Wikimedia\Message\MessageValue;

class ArchivedRevisionHandler extends AbstractRevisionHandler {

	/** @var ArchivedRevisionLookup */
	private $archivedRevisionLookup;

	/**
	 * @param InfoManager $infoManager
	 * @param ArchivedRevisionLookup $archivedRevisionLookup
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param DefaultPresenter $presenter
	 * @param JobQueueGroup $jobQueueGroup
	 * @param LanguageFallback $languageFallback
	 */
	public function __construct(
		InfoManager $infoManager,
		ArchivedRevisionLookup $archivedRevisionLookup,
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
			$languageFallback
		);
		$this->archivedRevisionLookup = $archivedRevisionLookup;
	}

	/**
	 * @param InfoManager $infoManager
	 * @param ArchivedRevisionLookup $archivedRevisionLookup
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param JobQueueGroup $jobQueueGroup
	 * @param LanguageFallback $languageFallback
	 * @return self
	 */
	public static function factory(
		InfoManager $infoManager,
		ArchivedRevisionLookup $archivedRevisionLookup,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback
	) {
		return new self(
			$infoManager,
			$archivedRevisionLookup,
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
	protected function getRevision( int $id ): ?RevisionRecord {
		if ( !$this->permissionManager->userHasRight( $this->getAuthority()->getUser(), 'deletedhistory' ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-access-denied' ),
				$this->getAuthority()->getUser()->isRegistered() ? 403 : 401 );
		}

		return $this->archivedRevisionLookup->getArchivedRevisionRecord( null, $id );
	}
}
