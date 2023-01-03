<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use JobQueueGroup;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use RequestContext;

class RevisionHandler extends AbstractRevisionHandler {

	/** @var RevisionLookup */
	private $revisionLookup;

	/**
	 * @param InfoManager $infoManager
	 * @param RevisionLookup $revisionLookup
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param UserIdentity $user
	 * @param DefaultPresenter $presenter
	 * @param JobQueueGroup $jobQueueGroup
	 * @param LanguageFallback $languageFallback
	 */
	public function __construct(
		InfoManager $infoManager,
		RevisionLookup $revisionLookup,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		UserIdentity $user,
		DefaultPresenter $presenter,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback
	) {
		parent::__construct(
			$infoManager,
			$permissionManager,
			$userOptionsLookup,
			$userFactory,
			$user,
			$presenter,
			$jobQueueGroup,
			$languageFallback
		);
		$this->revisionLookup = $revisionLookup;
	}

	/**
	 * @param InfoManager $infoManager
	 * @param RevisionLookup $revisionLookup
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param JobQueueGroup $jobQueueGroup
	 * @param LanguageFallback $languageFallback
	 * @return self
	 */
	public static function factory(
		InfoManager $infoManager,
		RevisionLookup $revisionLookup,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback
	) {
		return new self(
			$infoManager,
			$revisionLookup,
			$permissionManager,
			$userOptionsLookup,
			$userFactory,
			// @TODO Replace with something better.
			RequestContext::getMain()->getUser(),
			new DefaultPresenter( $permissionManager ),
			$jobQueueGroup,
			$languageFallback
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getRevision( int $id ): ?RevisionRecord {
		return $this->revisionLookup->getRevisionById( $id );
	}
}
