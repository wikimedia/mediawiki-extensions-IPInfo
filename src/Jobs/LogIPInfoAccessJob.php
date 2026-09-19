<?php

declare( strict_types=1 );

namespace MediaWiki\IPInfo\Jobs;

use MediaWiki\IPInfo\Logging\LoggerFactory;
use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\Job;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;

/**
 * Log when a user accesses information about an ip
 */
class LogIPInfoAccessJob extends Job {
	public const JOB_TYPE = 'ipinfoLogIPInfoAccess';

	public function __construct(
		array $params,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly LoggerFactory $ipInfoLoggerFactory,
	) {
		parent::__construct( self::JOB_TYPE, $params );
	}

	/**
	 * Create a new specification for this job with the given parameters.
	 *
	 * @param UserIdentity $accessingUser The user that accessed IP information
	 * @param string $targetName Name of the user whose IP information was accessed
	 * @param string $dataContext 'infobox' or 'popup'
	 * @param string|null $accessLevel The access level of the user that accessed IP information
	 * @return IJobSpecification
	 */
	public static function newSpecification(
		UserIdentity $accessingUser,
		string $targetName,
		string $dataContext,
		?string $accessLevel
	): IJobSpecification {
		return new JobSpecification(
			self::JOB_TYPE,
			[
				'performer' => $accessingUser->getName(),
				'targetName' => $targetName,
				'dataContext' => $dataContext,
				'timestamp' => (int)wfTimestamp(),
				'access_level' => $accessLevel,
			],
			[],
			null
		);
	}

	/**
	 * @return bool
	 */
	public function run() {
		$performer = $this->userIdentityLookup->getUserIdentityByName( $this->params['performer'] );
		// Accept 'ip' param as B/C for inflight jobs
		$targetName = $this->params['targetName'] ?? $this->params['ip'];
		$timestamp = $this->params['timestamp'];
		$level = $this->params['access_level'];

		if ( !$performer ) {
			$this->setLastError( 'Invalid performer' );
			return false;
		}

		$logger = $this->ipInfoLoggerFactory->getLogger();

		switch ( $this->params['dataContext'] ) {
			case 'infobox':
				$logger->logViewInfobox( $performer, $targetName, $timestamp, $level );
				break;
			case 'popup':
				$logger->logViewPopup( $performer, $targetName, $timestamp, $level );
				break;
			default:
				$this->setLastError( 'Invalid dataContext: ' . $this->params['dataContext'] );
				return false;
		}
		return true;
	}
}
