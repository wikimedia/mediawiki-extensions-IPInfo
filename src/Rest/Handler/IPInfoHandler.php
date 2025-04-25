<?php

namespace MediaWiki\IPInfo\Rest\Handler;

use MediaWiki\IPInfo\AccessLevelTrait;
use MediaWiki\IPInfo\Hook\IPInfoHookRunner;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\Jobs\LogIPInfoAccessJob;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\ReadOnlyMode;

abstract class IPInfoHandler extends SimpleHandler {

	use AccessLevelTrait;
	use TokenAwareHandlerTrait;

	/**
	 * An array of contexts and the data
	 * that should be available in those contexts
	 *
	 * @var array
	 */
	protected const VIEWING_CONTEXTS = [
		'popup' => [
			'country',
			'countryNames',
			'location',
			'organization',
			'numActiveBlocks',
			'numLocalEdits',
			'numRecentEdits',
		],
		'infobox' => [
			'country',
			'countryNames',
			'location',
			'connectionType',
			'userType',
			'asn',
			'isp',
			'organization',
			'proxyType',
			'behaviors',
			'risks',
			'connectionTypes',
			'tunnelOperators',
			'proxies',
			'numUsersOnThisIP',
			'numIPAddresses',
			'numActiveBlocks',
			'numLocalEdits',
			'numRecentEdits',
			'numDeletedEdits',
			'version',
		]
	];

	protected InfoManager $infoManager;

	protected PermissionManager $permissionManager;

	protected UserOptionsLookup $userOptionsLookup;

	protected UserFactory $userFactory;

	protected DefaultPresenter $presenter;

	protected JobQueueGroup $jobQueueGroup;

	protected LanguageFallback $languageFallback;

	protected UserIdentityUtils $userIdentityUtils;

	protected TempUserIPLookup $tempUserIPLookup;

	private ExtensionRegistry $extensionRegistry;
	private ReadOnlyMode $readOnlyMode;
	private IPInfoHookRunner $ipInfoHookRunner;

	public function __construct(
		InfoManager $infoManager,
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
		$this->infoManager = $infoManager;
		$this->permissionManager = $permissionManager;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userFactory = $userFactory;
		$this->presenter = $presenter;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->languageFallback = $languageFallback;
		$this->userIdentityUtils = $userIdentityUtils;
		$this->tempUserIPLookup = $tempUserIPLookup;
		$this->extensionRegistry = $extensionRegistry;
		$this->readOnlyMode = $readOnlyMode;
		$this->ipInfoHookRunner = $ipInfoHookRunner;
	}

	/**
	 * Get information about an IP address (or IP addresses) associated with some entity,
	 * given an ID for the entity. An ID can either be:
	 * - an integer, representing an entity (log entry, revision) id (consumed by (*)Revision/LogHandler)
	 * - a string, representing a username or IP (consumed by NoRevisionHandler)
	 *
	 * Concrete subclasses handle for specific entity types, e.g. revision, log entry, etc.
	 *
	 * @param int|string $id
	 * @return array[]
	 *  Each array in this array has the following structure:
	 *  - 'subject': IP address
	 *  - 'data': array of arrays, each with the following structure:
	 *    - data source as a string => data array
	 *  TODO: Task to codify this better
	 */
	abstract protected function getInfo( $id ): array;

	/**
	 * Return information about an IP address given one of the following identifiers:
	 * - an integer, representing an entity (log entry, revision) id (consumed by (*)Revision/LogHandler)
	 * - a string, representing a username or IP (consumed by NoRevisionHandler)
	 *
	 * @param string|int $id
	 * @return Response
	 */
	public function run( $id ): Response {
		$isBetaFeaturesLoaded = $this->extensionRegistry->isLoaded( 'BetaFeatures' );
		// Disallow access to API if BetaFeatures is enabled but the feature is not
		if ( $isBetaFeaturesLoaded &&
			!$this->userOptionsLookup->getOption( $this->getAuthority()->getUser(), 'ipinfo-beta-feature-enable' ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-access-denied' ),
				$this->getAuthority()->getUser()->isRegistered() ? 403 : 401
			);
		}

		if (
			!$this->permissionManager->userHasRight( $this->getAuthority()->getUser(), 'ipinfo' ) ||
			!$this->userOptionsLookup->getOption( $this->getAuthority()->getUser(), 'ipinfo-use-agreement' )
		) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-access-denied' ),
				$this->getAuthority()->getUser()->isRegistered() ? 403 : 401
			);
		}
		$user = $this->userFactory->newFromUserIdentity( $this->getAuthority()->getUser() );

		$block = $user->getBlock();

		// Users with sitewide blocks on their accounts shouldn't be allowed to view ip info
		if ( $block && $block->isSitewide() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'ipinfo-rest-access-denied-blocked-user' ),
				403
			);
		}

		// Validate the CSRF token. We shouldn't need to allow anon CSRF tokens.
		$this->validateToken();

		// If the site is in read only mode, we should return an error because we need to log accessing which creates
		// a database row.
		$readOnlyReason = $this->readOnlyMode->getReason();
		if ( $readOnlyReason ) {
			throw new LocalizedHttpException(
				new MessageValue( 'readonlytext', [ $readOnlyReason ] ),
				503
			);
		}

		$info = $this->getInfo( $id );
		$userLang = strtolower( $this->getValidatedParams()['language'] );
		$langCodes = array_unique( array_merge(
			[ $userLang ],
			$this->languageFallback->getAll( $userLang )
		) );

		// Only show data required for the context
		$dataContext = $this->getValidatedParams()['dataContext'];
		foreach ( $info as $index => $set ) {
			if ( !isset( $set['data'] ) ) {
				continue;
			}
			$info[$index] += [ 'language-fallback' => $langCodes ];
			foreach ( $set['data'] as $provider => $dataset ) {
				foreach ( $dataset as $datum => $value ) {
					if ( !in_array( $datum, self::VIEWING_CONTEXTS[$dataContext] ?? [] ) ) {
						unset( $info[$index]['data'][$provider][$datum] );
					}
				}
			}

			// Processing of dataset (context/permission unsets) for the subject is done by this point
			// so allow other extensions to append to this dataset with their own data.
			// IPInfo passes along the context via the hook and extensions are responsible
			// for managing their own permissions/contextual view logic.
			$otherData = [];
			$this->ipInfoHookRunner->onIPInfoHandlerRun(
				$set['subject'], $dataContext, $otherData
			);

			if ( count( $otherData ) ) {
				foreach ( $otherData as $source => $itemInfo ) {
					if ( !isset( $info[$index]['data'][$source] ) ) {
						$info[$index]['data'][$source] = [];
					}
					$info[$index]['data'][$source] = array_merge( $info[$index]['data'][$source], $itemInfo );
				}
			}
		}

		foreach ( $info as $index => $set ) {
			if ( !isset( $set['subject'] ) ) {
				continue;
			}
			$this->logAccess( $this->getAuthority()->getUser(), $set['subject'], $dataContext );
		}

		$response = $this->getResponseFactory()->createJson( [ 'info' => $info ] );
		$response->setHeader( 'Cache-Control', 'private, max-age=86400' );
		return $response;
	}

	/**
	 * Log that the IP information was accessed. See also LogIPInfoAccessJob.
	 *
	 * @param UserIdentity $accessingUser
	 * @param string $targetName Name of the user whose information was accessed
	 * @param string $dataContext 'infobox' or 'popup'
	 */
	protected function logAccess( $accessingUser, $targetName, $dataContext ): void {
		$level = $this->highestAccessLevel(
			$this->permissionManager->getUserPermissions( $accessingUser )
		);
		$this->jobQueueGroup->push(
			LogIPInfoAccessJob::newSpecification( $accessingUser, $targetName, $dataContext, $level )
		);
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
				'dataContext' => [
					self::PARAM_SOURCE => 'query',
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_REQUIRED => true,
				],
				'language' => [
					self::PARAM_SOURCE => 'query',
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_REQUIRED => true,
				],
			];
	}

	public function getBodyParamSettings(): array {
		return $this->getTokenParamDefinition();
	}

}
