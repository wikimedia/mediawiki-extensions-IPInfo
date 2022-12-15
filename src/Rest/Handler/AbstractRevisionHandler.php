<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Revision\RevisionRecord;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;

abstract class AbstractRevisionHandler extends IPInfoHandler {

	/**
	 * @inheritDoc
	 */
	protected function getInfo( int $id ): array {
		$revision = $this->getRevision( $id );

		if ( !$revision ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-nonexistent-revision', [ $id ] ), 404 );
		}

		$user = $this->userFactory->newFromUserIdentity( $this->user );
		if ( !$this->permissionManager->userCan( 'read', $user, $revision->getPageAsLinkTarget() ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-revision-permission-denied-revision', [ $id ] ), 403 );
		}

		$author = $revision->getUser( RevisionRecord::FOR_THIS_USER, $user );

		if ( !$author ) {
			// User does not have access to author.
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-revision-no-author' ), 403 );
		}

		if ( $author->isRegistered() ) {
			// Since the IP address only exists in CheckUser, there is no way to access it.
			// @TODO Allow extensions (like CheckUser) to either pass without a value
			//      (which would result in a 404) or throw a fatal (which could result in a 403).
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-revision-registered' ), 404 );
		}

		if ( !IPUtils::isValid( $author->getName() ) ) {
			// Not a valid IP and probably an imported edit
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-revision-invalid-ip' ), 404 );
		}

		$info = [
			$this->presenter->present( $this->infoManager->retrieveFromIP( $author->getName() ), $this->user )
		];

		return $info;
	}

	/**
	 * @param int $id ID of the revision
	 * @return RevisionRecord|null if the revision does not exist
	 */
	abstract protected function getRevision( int $id ): ?RevisionRecord;
}
