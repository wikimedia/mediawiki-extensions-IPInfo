<?php

namespace MediaWiki\IPInfo\RestHandler;

use MediaWiki\IPInfo\InfoManager;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\User\UserIdentity;
use RequestContext;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * @TODO Remove once T261555 is completed
 */
class IPHandler extends SimpleHandler {
	/** @var InfoManager */
	private $infoManager;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var UserIdentity */
	private $user;

	/**
	 * @param InfoManager $infoManager
	 * @param PermissionManager $permissionManager
	 * @param UserIdentity $user
	 */
	public function __construct(
		InfoManager $infoManager,
		PermissionManager $permissionManager,
		UserIdentity $user
	) {
		$this->infoManager = $infoManager;
		$this->permissionManager = $permissionManager;
		$this->user = $user;
	}

	/**
	 * @param InfoManager $infoManager
	 * @param PermissionManager $permissionManager
	 * @return self
	 */
	public static function factory(
		InfoManager $infoManager,
		PermissionManager $permissionManager
	) {
		return new self(
			$infoManager,
			$permissionManager,
			// @TODO Replace with something better.
			RequestContext::getMain()->getUser()
		);
	}

	/**
	 * Get IP Info for an IP address.
	 *
	 * @return Response
	 */
	public function run() : Response {
		[
			'ip' => $ip
		] = $this->getValidatedBody();

		if ( !IPUtils::isValid( $ip ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-ip-invalid' ), 400 );
		}

		if ( !$this->permissionManager->userHasRight( $this->user, 'ipinfo-any' ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-access-denied' ), $this->user->isRegistered() ? 403 : 401 );
		}

		$info = $this->infoManager->retrieveFromIP( $ip );

		return $this->getResponseFactory()->createJson( $info );
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyValidator( $contentType ) {
		if ( $contentType !== 'application/json' ) {
			throw new HttpException( "Unsupported Content-Type",
				415,
				[ 'content_type' => $contentType ]
			);
		}

		return new JsonBodyValidator( [
			'ip' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		] );
	}

}
