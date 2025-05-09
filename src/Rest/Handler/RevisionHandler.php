<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use MediaWiki\IPInfo\Hook\IPInfoHookRunner;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityUtils;
use Wikimedia\Rdbms\ReadOnlyMode;

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
		LanguageFallback $languageFallback,
		UserIdentityUtils $userIdentityUtils,
		TempUserIPLookup $tempUserIPLookup,
		ExtensionRegistry $extensionRegistry,
		ReadOnlyMode $readOnlyMode,
		IPInfoHookRunner $ipInfoHookRunner
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
			$extensionRegistry,
			$readOnlyMode,
			$ipInfoHookRunner
		);
		$this->revisionLookup = $revisionLookup;
	}

	public static function factory(
		InfoManager $infoManager,
		RevisionLookup $revisionLookup,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback,
		UserIdentityUtils $userIdentityUtils,
		TempUserIPLookup $tempUserIPLookup,
		ExtensionRegistry $extensionRegistry,
		ReadOnlyMode $readOnlyMode,
		IPInfoHookRunner $ipInfoHookRunner
	): self {
		return new self(
			$infoManager,
			$revisionLookup,
			$permissionManager,
			$userOptionsLookup,
			$userFactory,
			new DefaultPresenter( $permissionManager ),
			$jobQueueGroup,
			$languageFallback,
			$userIdentityUtils,
			$tempUserIPLookup,
			$extensionRegistry,
			$readOnlyMode,
			$ipInfoHookRunner
		);
	}

	/** @inheritDoc */
	protected function getRevision( int $id ): ?RevisionRecord {
		return $this->revisionLookup->getRevisionById( $id );
	}
}
