<?php
namespace MediaWiki\IPInfo\Special;

use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Exception\ThrottledError;
use MediaWiki\Exception\UserBlockedError;
use MediaWiki\Html\Html;
use MediaWiki\Html\TemplateParser;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\IPInfo\HookHandler\PreferencesHandler;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\InfoRetriever\GeoLite2InfoRetriever;
use MediaWiki\IPInfo\InfoRetriever\IPoidInfoRetriever;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\Linker\Linker;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNameUtils;
use Wikimedia\IPUtils;
use Wikimedia\ObjectCache\BagOStuff;

/**
 * A special page that displays IP information for all IP addresses used by a temporary user,
 * or for a single arbitrary IP address.
 */
class SpecialIPInfo extends FormSpecialPage {
	private const CONSTRUCTOR_OPTIONS = [
		'IPInfoMaxDistinctIPResults'
	];

	private const TARGET_FIELD = 'Target';
	private const IP_INFO_AGREEMENT_FIELD = 'AcceptAgreement';

	private const SORT_ASC = 'asc';
	private const SORT_DESC = 'desc';

	private readonly TemplateParser $templateParser;
	private readonly DefaultPresenter $defaultPresenter;
	private readonly ServiceOptions $serviceOptions;

	private UserIdentity $targetUser;
	private ?string $targetIp = null;

	public function __construct(
		private readonly UserOptionsManager $userOptionsManager,
		private readonly UserNameUtils $userNameUtils,
		BagOStuff $srvCache,
		private readonly TempUserIPLookup $tempUserIPLookup,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly InfoManager $infoManager,
		private readonly PermissionManager $permissionManager,
		Config $config
	) {
		parent::__construct( 'IPInfo', 'ipinfo' );
		$serviceOptions = new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $config );
		$serviceOptions->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$this->templateParser = new TemplateParser( __DIR__ . '/templates', $srvCache );
		$this->defaultPresenter = new DefaultPresenter( $this->permissionManager );
		$this->serviceOptions = $serviceOptions;
	}

	/** @inheritDoc */
	public function execute( $par ): void {
		$this->addHelpLink( 'Trust_and_Safety_Product/IP_Info' );

		$block = $this->getAuthority()->getBlock();
		if ( $block && $block->isSitewide() ) {
			throw new UserBlockedError(
				$block,
				$this->getAuthority()->getUser(),
				$this->getLanguage(),
				$this->getRequest()->getIP()
			);
		}

		parent::execute( $par );
	}

	private function didNotAcceptIPInfoAgreement(): bool {
		return !$this->userOptionsManager->getBoolOption(
			$this->getAuthority()->getUser(), PreferencesHandler::IPINFO_USE_AGREEMENT
		);
	}

	/**
	 * Check and accept the IP info data use agreement if needed.
	 *
	 * @param array $data Form data
	 * @return Status|null Fatal status if agreement is required but not accepted, null otherwise
	 */
	private function maybeAcceptAgreement( array $data ): ?Status {
		if ( $this->didNotAcceptIPInfoAgreement() ) {
			if ( !( $data[self::IP_INFO_AGREEMENT_FIELD] ?? false ) ) {
				return Status::newFatal( 'ipinfo-preference-agreement-error' );
			}

			$user = $this->getUser();
			$this->userOptionsManager->setOption(
				$user, PreferencesHandler::IPINFO_USE_AGREEMENT, '1', UserOptionsManager::GLOBAL_CREATE
			);
			$this->userOptionsManager->saveOptions( $user );
		}

		return null;
	}

	/** @inheritDoc */
	public function requiresPost(): bool {
		// POST the form if the agreement needs to be accepted to allow DB writes
		// for updating the corresponding preference.
		return $this->didNotAcceptIPInfoAgreement();
	}

	/** @inheritDoc */
	public function doesWrites(): bool {
		return $this->didNotAcceptIPInfoAgreement();
	}

	protected function getSubpageField(): string {
		return self::TARGET_FIELD;
	}

	protected function getFormFields(): array {
		$fields = [
			self::TARGET_FIELD => [
				'type' => 'user',
				'label-message' => 'ipinfo-special-ipinfo-target',
				'excludenamed' => true,
				'ipallowed' => true,
				'autocomplete' => 'on',
				'required' => true
			]
		];

		$request = $this->getRequest();

		// Require accepting the IP info data use agreement in order to view IP info
		if ( $this->didNotAcceptIPInfoAgreement() ) {
			// Workaround: Avoid showing the now-superfluous agreement checkbox after submission if the
			// user accepted the agreement, but keep it part of the form so that its value remains usable
			// by onSubmit().
			$willAcceptAgreement = $request->wasPosted() && $request->getCheck( 'wp' . self::IP_INFO_AGREEMENT_FIELD );
			$type = $willAcceptAgreement ? 'hidden' : 'check';

			$fields[self::IP_INFO_AGREEMENT_FIELD] = [
				'type' => $type,
				'label-message' => 'ipinfo-preference-use-agreement',
				'help-message' => 'ipinfo-infobox-use-terms',
				'required' => true
			];
		}

		return $fields;
	}

	protected function alterForm( HTMLForm $form ): void {
		$target = $this->getRequest()->getVal( 'wpTarget', $this->par ?? '' );
		if ( IPUtils::isValid( $target ) ) {
			$legend = $this->msg( 'ipinfo-special-ipinfo-ip-legend' )->parseAsBlock();
		} else {
			$legend = $this->msg( 'ipinfo-special-ipinfo-legend' )
				->numParams( $this->serviceOptions->get( 'IPInfoMaxDistinctIPResults' ) )
				->parseAsBlock();
		}

		$form->addHeaderHtml( $legend );
	}

	/** @inheritDoc */
	public function getDescription(): Message {
		return $this->msg( 'ipinfo-special-ipinfo' );
	}

	protected function getMessagePrefix(): string {
		// Possible message keys used here:
		// * ipinfo-special-ipinfo-form-text
		return 'ipinfo-special-ipinfo-form';
	}

	protected function getDisplayFormat(): string {
		// Use OOUI rather than Codex for this form
		// until a satisfactory solution for reusable MW-specific Codex widgets is devised (T334986).
		return 'ooui';
	}

	protected function getShowAlways(): bool {
		return true;
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @return string
	 */
	protected function getGroupName(): string {
		return 'users';
	}

	/**
	 * Process form data on submission.
	 * @param array $data Map of form data keyed by unprefixed field name
	 * @return Status
	 */
	public function onSubmit( array $data ): Status {
		$targetName = trim( $data[self::TARGET_FIELD] );

		// Arbitrary IP address lookup
		if ( IPUtils::isValid( $targetName ) ) {
			if ( !$this->permissionManager->userHasRight(
				$this->getUser(), 'ipinfo-view-arbitrary-ip'
			) ) {
				return Status::newFatal( 'ipinfo-special-ipinfo-no-right-ip' );
			}

			if ( $this->getUser()->pingLimiter( 'ipinfo-ip-lookup' ) ) {
				throw new ThrottledError();
			}

			$this->targetIp = IPUtils::sanitizeIP( $targetName );

			$agreementStatus = $this->maybeAcceptAgreement( $data );
			if ( $agreementStatus !== null ) {
				return $agreementStatus;
			}

			return Status::newGood();
		}

		if ( !$this->userNameUtils->isTemp( $targetName ) ) {
			return Status::newFatal( 'htmlform-user-not-valid', $targetName );
		}

		$targetUser = $this->userIdentityLookup->getUserIdentityByName( $targetName );
		if ( $targetUser === null ) {
			return Status::newFatal( 'htmlform-user-not-valid', $targetName );
		}

		$agreementStatus = $this->maybeAcceptAgreement( $data );
		if ( $agreementStatus !== null ) {
			return $agreementStatus;
		}

		$this->targetUser = $targetUser;

		return Status::newGood();
	}

	/** @inheritDoc */
	public function onSuccess(): void {
		if ( $this->targetIp !== null ) {
			$this->onSuccessForIp();
		} else {
			$this->onSuccessForTempUser();
		}
	}

	/**
	 * Build table headers for IP information display.
	 *
	 * @param bool $sortable Whether the columns should be sortable.
	 * @return array[] Table header definitions.
	 */
	private function getTableHeaders( bool $sortable ): array {
		return [
			[
				'name' => 'address',
				'title' => $this->msg( 'ipinfo-special-ipinfo-column-ip' )->text(),
				'sortable' => false
			],
			[
				'name' => 'location',
				'title' => $this->msg( 'ipinfo-property-label-location' )->text(),
				'sortable' => $sortable
			],
			[
				'name' => 'asn',
				'title' => $this->msg( 'ipinfo-property-label-asn' )->text(),
				'sortable' => $sortable
			],
			[
				'name' => 'organization',
				'title' => $this->msg( 'ipinfo-property-label-organization' )->text(),
				'sortable' => $sortable
			],
			[
				'name' => 'ipversion',
				'title' => $this->msg( 'ipinfo-property-label-ipversion' )->text(),
				'sortable' => $sortable
			],
			[
				'name' => 'behaviors',
				'title' => $this->msg( 'ipinfo-property-label-behaviors' )->text(),
				'sortable' => $sortable
			],
			[
				'name' => 'risks',
				'title' => $this->msg( 'ipinfo-property-label-risks' )->text(),
				'sortable' => $sortable
			],
			[
				'name' => 'connectiontypes',
				'title' => $this->msg( 'ipinfo-property-label-connectiontypes' )->text(),
				'sortable' => $sortable
			],
			[
				'name' => 'tunneloperators',
				'title' => $this->msg( 'ipinfo-property-label-tunneloperators' )->text(),
				'sortable' => $sortable
			],
			[
				'name' => 'proxies',
				'title' => $this->msg( 'ipinfo-property-label-proxies' )->text(),
				'sortable' => $sortable
			],
			[
				'name' => 'usercount',
				'title' => $this->msg( 'ipinfo-property-label-usercount' )->text(),
				'sortable' => $sortable
			],
		];
	}

	/**
	 * Convert presented IP info data into a table row.
	 *
	 * @param array $presented The output of DefaultPresenter::present()
	 * @param string $ip The IP address
	 * @return array Table row data
	 */
	private function buildTableRow( array $presented, string $ip ): array {
		$commaMsg = $this->msg( 'comma-separator' )->text();

		$locations = array_map(
			static fn ( array $loc ): string => $loc['label'],
			$presented['data']['ipinfo-source-geoip2']['location'] ?? []
		);

		$risks = array_map(
			function ( string $riskType ): string {
				$riskType = preg_replace( '/_/', '', $riskType );
				$riskType = mb_strtolower( $riskType );

				// See https://docs.spur.us/data-types?id=risk-enums
				// * ipinfo-property-value-risk-adfraud
				// * ipinfo-property-value-risk-callbackproxy
				// * ipinfo-property-value-risk-geomismatch
				// * ipinfo-property-value-risk-loginbruteforce
				// * ipinfo-property-value-risk-tunnel
				// * ipinfo-property-value-risk-webscraping
				// * ipinfo-property-value-risk-unknown
				return $this->msg( "ipinfo-property-value-risk-$riskType" )->text();
			},
			$presented['data']['ipinfo-source-ipoid']['risks'] ?? []
		);

		$connectionTypes = array_map(
			function ( string $connectionType ): string {
				$connectionType = mb_strtolower( $connectionType );

				// See https://docs.spur.us/data-types?id=client-enums
				// * ipinfo-property-value-connectiontype-desktop
				// * ipinfo-property-value-connectiontype-headless
				// * ipinfo-property-value-connectiontype-iot
				// * ipinfo-property-value-connectiontype-mobile
				// * ipinfo-property-value-connectiontype-unknown
				return $this->msg( "ipinfo-property-value-connectiontype-$connectionType" )->text();
			},
			$presented['data']['ipinfo-source-ipoid']['connectionTypes'] ?? []
		);

		$userCount = $presented['data']['ipinfo-source-ipoid']['numUsersOnThisIP'] ?? null;

		return [
			'location' => implode( $commaMsg, $locations ),
			'asn' => $presented['data']['ipinfo-source-geoip2']['asn'] ?? '',
			'organization' => $presented['data']['ipinfo-source-geoip2']['organization'] ?? '',
			'ipversion' => IPUtils::isIPv4( $ip )
				? $this->msg( 'ipinfo-value-ipversion-ipv4' )->text()
				: $this->msg( 'ipinfo-value-ipversion-ipv6' )->text(),
			'behaviors' => $presented['data']['ipinfo-source-ipoid']['behaviors'] ?? '',
			'risks' => $risks,
			'connectiontypes' => $connectionTypes,
			'tunneloperators' => $presented['data']['ipinfo-source-ipoid']['tunnelOperators'] ?? [],
			'proxies' => $presented['data']['ipinfo-source-ipoid']['proxies'] ?? [],
			'usercount' => $userCount !== null ? $this->getLanguage()->formatNum( $userCount ) : ''
		];
	}

	/**
	 * Display IP information for a single arbitrary IP address.
	 */
	private function onSuccessForIp(): void {
		LoggerFactory::getInstance( 'IPInfo' )->info(
			'Special:IPInfo arbitrary IP lookup by {user}',
			[ 'user' => $this->getUser()->getName() ]
		);

		$out = $this->getOutput();
		$out->addModuleStyles( [ 'codex-styles', 'ext.ipInfo.specialIpInfo' ] );

		$ip = $this->targetIp;
		$info = $this->infoManager->retrieveFor( $ip, $ip );
		$presented = $this->defaultPresenter->present( $info, $this->getContext()->getUser() );

		$prettyIp = IPUtils::prettifyIP( $ip );

		// Exclude the address column header since the IP is already shown in the caption
		$headers = array_values( array_filter(
			$this->getTableHeaders( false ),
			static fn ( array $header ) => $header['name'] !== 'address'
		) );

		$out->addHTML(
			$this->templateParser->processTemplate( 'IPInfo', [
				'caption' => $this->msg( 'ipinfo-special-ipinfo-table-caption-ip', $prettyIp )->text(),
				'sortExplainerCaption' => '',
				'showAddress' => false,
				'target' => $prettyIp,
				'headers' => $headers,
				'rows' => [ $this->buildTableRow( $presented, $ip ) ],
			] )
		);
	}

	/**
	 * Display IP information for all IP addresses used by a temporary user.
	 */
	private function onSuccessForTempUser(): void {
		$out = $this->getOutput();
		$out->addModuleStyles( [ 'codex-styles', 'ext.ipInfo.specialIpInfo' ] );

		$out->addSubtitle(
			$this->msg( 'ipinfo-special-ipinfo-user-tool-links', $this->targetUser->getName() )->escaped() .
			Linker::userToolLinks( $this->targetUser->getId(), $this->targetUser->getName() )
		);

		$records = $this->tempUserIPLookup->getDistinctIPInfo( $this->targetUser );

		if ( count( $records ) === 0 ) {
			$zeroStateMsg = $this->msg( 'ipinfo-special-ipinfo-no-results', $this->targetUser->getName() )->escaped();
			$out->addHTML( Html::noticeBox( $zeroStateMsg, 'ext-ipinfo-special-ipinfo__zero-state' ) );
			return;
		}

		$tableHeaders = $this->getTableHeaders( true );

		$batch = $this->infoManager->retrieveBatch(
			$this->targetUser,
			array_keys( $records ),
			[
				GeoLite2InfoRetriever::NAME,
				IPoidInfoRetriever::NAME
			]
		);

		$tableRows = [];
		foreach ( $records as $record ) {
			$info = $batch[$record->getIp()];
			$presented = $this->defaultPresenter->present( $info, $this->getContext()->getUser() );

			$row = $this->buildTableRow( $presented, $record->getIp() );
			$row['revId'] = $record->getRevisionId();
			$row['logId'] = $record->getLogId();

			$tableRows[] = $row;
		}

		$sortField = $this->getRequest()->getRawVal( 'wpSortField' );
		$sortDirection = $this->getRequest()->getRawVal( 'wpSortDirection' );
		foreach ( $tableHeaders as &$header ) {
			if ( $header['sortable'] ) {
				$header['isSorted'] = $sortField === $header['name'] && $sortDirection !== null;
				if ( $header['isSorted'] ) {
					// Possible CSS classes that may be used here:
					// - ext-ipinfo-special-ipinfo__column--asc
					// - ext-ipinfo-special-ipinfo__column--desc
					$header['sortIconClass'] = "ext-ipinfo-special-ipinfo__column--{$sortDirection}";
					$header['ariaSort'] = $sortDirection === self::SORT_ASC ? 'ascending' : 'descending';
				} else {
					$header['sortIconClass'] = 'ext-ipinfo-special-ipinfo__column--unsorted';
				}

				$header['nextSortDirection'] = $sortDirection === self::SORT_ASC ? self::SORT_DESC :
					self::SORT_ASC;
			}
		}

		if ( $sortField !== null && $sortDirection !== null ) {
			usort(
				$tableRows,
				static function ( array $row, array $otherRow ) use ( $sortDirection, $sortField ): int {
					return $sortDirection === self::SORT_ASC
						? $row[$sortField] <=> $otherRow[$sortField]
						: $otherRow[$sortField] <=> $row[$sortField];
				}
			);
		}

		$out->addHTML(
			$this->templateParser->processTemplate( 'IPInfo', [
				'caption' => $this->msg( 'ipinfo-special-ipinfo-table-caption', $this->targetUser->getName() )->text(),
				// Describe the functionality of sorting buttons to assistive technologies
				// such as screen readers.
				'sortExplainerCaption' => $this->msg( 'ipinfo-special-ipinfo-sort-explainer' )->text(),
				'showAddress' => true,
				'target' => $this->targetUser->getName(),
				'headers' => $tableHeaders,
				'rows' => $tableRows,
			] )
		);
	}
}
