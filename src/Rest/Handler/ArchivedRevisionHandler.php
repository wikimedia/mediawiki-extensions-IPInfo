<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use JobQueueGroup;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use RequestContext;
use Wikimedia\Message\MessageValue;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class ArchivedRevisionHandler extends AbstractRevisionHandler {

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var RevisionStore */
	private $revisionStore;

	/**
	 * @param InfoManager $infoManager
	 * @param ILoadBalancer $loadBalancer
	 * @param RevisionStore $revisionStore
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
		ILoadBalancer $loadBalancer,
		RevisionStore $revisionStore,
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
		$this->loadBalancer = $loadBalancer;
		$this->revisionStore = $revisionStore;
	}

	/**
	 * @param InfoManager $infoManager
	 * @param ILoadBalancer $loadBalancer
	 * @param RevisionStore $revisionStore
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param JobQueueGroup $jobQueueGroup
	 * @param LanguageFallback $languageFallback
	 * @return self
	 */
	public static function factory(
		InfoManager $infoManager,
		ILoadBalancer $loadBalancer,
		RevisionStore $revisionStore,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		JobQueueGroup $jobQueueGroup,
		LanguageFallback $languageFallback
	) {
		return new self(
			$infoManager,
			$loadBalancer,
			$revisionStore,
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
		if ( !$this->permissionManager->userHasRight( $this->user, 'deletedhistory' ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-access-denied' ), $this->user->isRegistered() ? 403 : 401 );
		}

		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );
		$query = $this->revisionStore->getArchiveQueryInfo();
		$row = $this->getRevisionFromTable( $dbr, $query, $id );

		if ( !$row ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-nonexistent-revision', [ $id ] ), 404 );
		}

		return $this->revisionStore->newRevisionFromArchiveRow( $row );
	}

	/**
	 * @param IDatabase $dbr
	 * @param array $query
	 * @param int $id
	 * @return \stdClass|false
	 */
	protected function getRevisionFromTable( $dbr, $query, $id ) {
		return $dbr->newSelectQueryBuilder()
			->tables( $query['tables'] )
			->select(
				array_merge(
					$query['fields'],
					[ 'ar_namespace', 'ar_title' ]
				)
			)
			->where( [ 'ar_rev_id' => $id ] )
			->joinConds( $query['joins'] )
			->caller( __METHOD__ )
			->fetchRow();
	}
}
