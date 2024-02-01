<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use JobQueueGroup;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserFactory;

class RevisionHandler extends AbstractRevisionHandler {

	private RevisionLookup $revisionLookup;

	public function __construct(
		InfoManager $infoManager,
		RevisionLookup $revisionLookup,
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
			new DefaultPresenter( $permissionManager ),
			$jobQueueGroup,
			$languageFallback
		);
	}

	/** @inheritDoc */
	protected function getRevision( int $id ): ?RevisionRecord {
		return $this->revisionLookup->getRevisionById( $id );
	}
}
