<?php
namespace MediaWiki\IPInfo\Special;

use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Html\Html;
use MediaWiki\Html\TemplateParser;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\IPInfo\InfoManager;
use MediaWiki\IPInfo\InfoRetriever\GeoLite2InfoRetriever;
use MediaWiki\IPInfo\InfoRetriever\IPoidInfoRetriever;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\IPInfo\TempUserIPLookup;
use MediaWiki\Linker\Linker;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNameUtils;
use UserBlockedError;
use Wikimedia\IPUtils;
use Wikimedia\ObjectCache\BagOStuff;

/**
 * A special page that displays IP information for all IP addresses used by a temporary user.
 */
class SpecialIPInfo extends FormSpecialPage {
	private const CONSTRUCTOR_OPTIONS = [
		'IPInfoMaxDistinctIPResults'
	];

	private const TARGET_FIELD = 'Target';
	private const IP_INFO_AGREEMENT_FIELD = 'AcceptAgreement';

	private const SORT_ASC = 'asc';
	private const SORT_DESC = 'desc';

	private UserOptionsLookup $userOptionsManager;
	private UserNameUtils $userNameUtils;
	private TemplateParser $templateParser;
	private TempUserIPLookup $tempUserIPLookup;
	private UserIdentityLookup $userIdentityLookup;
	private InfoManager $infoManager;
	private DefaultPresenter $defaultPresenter;
	private ServiceOptions $serviceOptions;

	private UserIdentity $targetUser;

	public function __construct(
		UserOptionsManager $userOptionsManager,
		UserNameUtils $userNameUtils,
		BagOStuff $srvCache,
		TempUserIPLookup $tempUserIPLookup,
		UserIdentityLookup $userIdentityLookup,
		InfoManager $infoManager,
		PermissionManager $permissionManager,
		Config $config
	) {
		parent::__construct( 'IPInfo', 'ipinfo' );
		$serviceOptions = new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $config );
		$serviceOptions->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$this->userOptionsManager = $userOptionsManager;
		$this->userNameUtils = $userNameUtils;
		$this->templateParser = new TemplateParser( __DIR__ . '/templates', $srvCache );
		$this->tempUserIPLookup = $tempUserIPLookup;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->infoManager = $infoManager;
		$this->defaultPresenter = new DefaultPresenter( $permissionManager );
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
		return !$this->userOptionsManager->getBoolOption( $this->getAuthority()->getUser(), 'ipinfo-use-agreement' );
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
				'autocomplete' => 'on',
				'exists' => true,
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
		$legend = $this->msg( 'ipinfo-special-ipinfo-legend' )
			->numParams( $this->serviceOptions->get( 'IPInfoMaxDistinctIPResults' ) )
			->parseAsBlock();

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
		$targetName = $data[self::TARGET_FIELD];

		if ( !$this->userNameUtils->isTemp( $targetName ) ) {
			return Status::newFatal( 'htmlform-user-not-valid', $targetName );
		}

		$targetUser = $this->userIdentityLookup->getUserIdentityByName( $targetName );
		if ( $targetUser === null ) {
			return Status::newFatal( 'htmlform-user-not-valid', $targetName );
		}

		if ( $this->didNotAcceptIPInfoAgreement() ) {
			if ( !( $data[self::IP_INFO_AGREEMENT_FIELD] ?? false ) ) {
				return Status::newFatal( 'ipinfo-preference-agreement-error' );
			}

			$user = $this->getUser()->getInstanceForUpdate();
			$this->userOptionsManager->setOption( $user, 'ipinfo-use-agreement', '1' );
			$user->saveSettings();
		}

		$this->targetUser = $targetUser;

		return Status::newGood();
	}

	/** @inheritDoc */
	public function onSuccess(): void {
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

		$tableHeaders = [
			[
				'name' => 'address',
				'title' => $this->msg( 'ipinfo-special-ipinfo-column-ip' )->text(),
				'sortable' => false
			],
			[
				'name' => 'location',
				'title' => $this->msg( 'ipinfo-property-label-location' )->text(),
				'sortable' => true
			],
			[
				'name' => 'isp',
				'title' => $this->msg( 'ipinfo-property-label-isp' )->text(),
				'sortable' => true
			],
			[
				'name' => 'asn',
				'title' => $this->msg( 'ipinfo-property-label-asn' )->text(),
				'sortable' => true
			],
			[
				'name' => 'organization',
				'title' => $this->msg( 'ipinfo-property-label-organization' )->text(),
				'sortable' => true
			],
			[
				'name' => 'ipversion',
				'title' => $this->msg( 'ipinfo-property-label-ipversion' )->text(),
				'sortable' => true
			],
			[
				'name' => 'behaviors',
				'title' => $this->msg( 'ipinfo-property-label-behaviors' )->text(),
				'sortable' => true
			],
			[
				'name' => 'risks',
				'title' => $this->msg( 'ipinfo-property-label-risks' )->text(),
				'sortable' => true
			],
			[
				'name' => 'connectiontypes',
				'title' => $this->msg( 'ipinfo-property-label-connectiontypes' )->text(),
				'sortable' => true
			],
			[
				'name' => 'tunneloperators',
				'title' => $this->msg( 'ipinfo-property-label-tunneloperators' )->text(),
				'sortable' => true
			],
			[
				'name' => 'proxies',
				'title' => $this->msg( 'ipinfo-property-label-proxies' )->text(),
				'sortable' => true
			],
			[
				'name' => 'usercount',
				'title' => $this->msg( 'ipinfo-property-label-usercount' )->text(),
				'sortable' => true
			],
		];

		$tableRows = [];

		$commaMsg = $this->msg( 'comma-separator' )->text();
		$ipv4Msg = $this->msg( 'ipinfo-value-ipversion-ipv4' )->text();
		$ipv6Msg = $this->msg( 'ipinfo-value-ipversion-ipv6' )->text();

		$batch = $this->infoManager->retrieveBatch(
			$this->targetUser,
			array_keys( $records ),
			[
				GeoLite2InfoRetriever::NAME,
				IPoidInfoRetriever::NAME
			]
		);

		foreach ( $records as $record ) {
			$info = $batch[$record->getIp()];
			$info = $this->defaultPresenter->present( $info, $this->getContext()->getUser() );

			$locations = array_map(
				static fn ( array $loc ): string => $loc['label'],
				$info['data']['ipinfo-source-geoip2']['location'] ?? []
			);

			$risks = array_map(
				function ( string $riskType ): string {
					$riskType = preg_replace( '/_/', '', $riskType );
					$riskType = mb_strtolower( $riskType );

					// See https://docs.spur.us/data-types?id=risk-enums
					// * ipinfo-property-value-risk-callbackproxy
					// * ipinfo-property-value-risk-geomismatch
					// * ipinfo-property-value-risk-loginbruteforce
					// * ipinfo-property-value-risk-tunnel
					// * ipinfo-property-value-risk-webscraping
					// * ipinfo-property-value-risk-unknown
					return $this->msg( "ipinfo-property-value-risk-$riskType" )->text();
				},
				$info['data']['ipinfo-source-ipoid']['risks'] ?? []
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
					return $this->msg( "ipinfo-property-value-connectionType-$connectionType" )->text();
				},
				$info['data']['ipinfo-source-ipoid']['connectionTypes'] ?? []
			);

			$userCount = $info['data']['ipinfo-source-ipoid']['numUsersOnThisIP'] ?? null;

			$tableRows[] = [
				'revId' => $record->getRevisionId(),
				'logId' => $record->getLogId(),
				'location' => implode( $commaMsg, $locations ),
				'isp' => $info['data']['ipinfo-source-geoip2']['isp'] ?? '',
				'asn' => $info['data']['ipinfo-source-geoip2']['asn'] ?? '',
				'organization' => $info['data']['ipinfo-source-geoip2']['organization'] ?? '',
				'ipversion' => IPUtils::isIPv4( $record->getIp() ) ? $ipv4Msg : $ipv6Msg,
				'behaviors' => $info['data']['ipinfo-source-ipoid']['behaviors'] ?? '',
				'risks' => $risks,
				'connectiontypes' => $connectionTypes,
				'tunneloperators' => $info['data']['ipinfo-source-ipoid']['tunneloperators'] ?? [],
				'proxies' => $info['data']['ipinfo-source-ipoid']['proxies'] ?? [],
				'usercount' => $userCount !== null ? $this->getLanguage()->formatNum( $userCount ) : ''
			];
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
				'target' => $this->targetUser->getName(),
				'headers' => $tableHeaders,
				'rows' => $tableRows,
			] )
		);
	}
}
