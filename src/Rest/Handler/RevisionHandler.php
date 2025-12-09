<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\IPInfoPermissionManager;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityUtils;
use Wikimedia\Rdbms\ReadOnlyMode;

/**
 * @internal For use by IPInfo
 */
class RevisionHandler extends AbstractRevisionHandler {

	public function __construct(
		InfoManager $infoManager,
		private readonly RevisionLookup $revisionLookup,
		PermissionManager $permissionManager,
		UserFactory $userFactory,
		DefaultPresenter $presenter,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback,
		UserIdentityUtils $userIdentityUtils,
		TempUserIPLookup $tempUserIPLookup,
		IPInfoPermissionManager $ipInfoPermissionManager,
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
	}

	public static function factory(
		InfoManager $infoManager,
		RevisionLookup $revisionLookup,
		PermissionManager $permissionManager,
		UserFactory $userFactory,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback,
		UserIdentityUtils $userIdentityUtils,
		TempUserIPLookup $tempUserIPLookup,
		IPInfoPermissionManager $ipInfoPermissionManager,
		ReadOnlyMode $readOnlyMode,
		HookContainer $hookContainer
	): self {
		return new self(
			$infoManager,
			$revisionLookup,
			$permissionManager,
			$userFactory,
			new DefaultPresenter( $permissionManager ),
			$jobQueueGroup,
			$languageFallback,
			$userIdentityUtils,
			$tempUserIPLookup,
			$ipInfoPermissionManager,
			$readOnlyMode,
			$hookContainer
		);
	}

	/** @inheritDoc */
	protected function getRevision( int $id ): ?RevisionRecord {
		return $this->revisionLookup->getRevisionById( $id );
	}
}
