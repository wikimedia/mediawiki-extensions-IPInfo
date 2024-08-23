<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Revision\RevisionRecord;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;

abstract class AbstractRevisionHandler extends IPInfoHandler {

	/** @inheritDoc */
	protected function getInfo( int $id ): array {
		$revision = $this->getRevision( $id );

		if ( !$revision ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-nonexistent-revision', [ $id ] ),
				404
			);
		}

		$user = $this->userFactory->newFromUserIdentity( $this->getAuthority()->getUser() );
		if ( !$this->permissionManager->userCan( 'read', $user, $revision->getPageAsLinkTarget() ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-revision-permission-denied-revision', [ $id ] ),
				403
			);
		}

		$author = $revision->getUser( RevisionRecord::FOR_THIS_USER, $this->getAuthority() );

		if ( !$author ) {
			// User does not have access to author.
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-revision-no-author' ),
				403
			);
		}

		if ( $this->userIdentityUtils->isNamed( $author ) ) {
			// By design, IPInfo currently only supports retrieving IP data for anonymous or temporary users.
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-revision-registered' ),
				404
			);
		}

		if ( !( $this->userIdentityUtils->isTemp( $author ) || IPUtils::isValid( $author->getName() ) ) ) {
			// Not a valid IP or temporary account; may be an imported edit
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-revision-invalid-ip' ),
				404
			);
		}

		return [
			$this->presenter->present(
				$this->infoManager->retrieveFor( $author ),
				$this->getAuthority()->getUser()
			)
		];
	}

	/**
	 * @param int $id ID of the revision
	 * @return RevisionRecord|null if the revision does not exist
	 */
	abstract protected function getRevision( int $id ): ?RevisionRecord;
}
