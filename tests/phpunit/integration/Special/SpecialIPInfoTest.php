<?php
namespace MediaWiki\IPInfo\Tests\Integration\Special;

use Closure;
use DOMDocument;
use DOMNode;
use HtmlFormatter\HtmlFormatter;
use MediaWiki\Context\RequestContext;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWiki\IPInfo\Special\SpecialIPInfo;
use MediaWiki\Permissions\Authority;
use MediaWiki\Request\FauxRequest;
use PermissionsError;
use SpecialPageTestBase;
use UserBlockedError;
use Wikimedia\Parsoid\Utils\DOMCompat;

/**
 * @covers \MediaWiki\IPInfo\Special\SpecialIPInfo
 * @group Database
 */
class SpecialIPInfoTest extends SpecialPageTestBase {
	private static Authority $tempUserWithEdits;

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		$this->overrideConfigValues( [
			'IPInfoEnableSpecialIPInfo' => true,
			'IPInfoIpoidUrl' => false,
			'IPInfoGeoLite2Prefix' => realpath( __DIR__ . '/../../../fixtures' ) . '/maxmind/GeoLite2-',
		] );
		$this->setGroupPermissions( [
			'sysop' => [
				'ipinfo' => true,
				DefaultPresenter::IPINFO_VIEW_BASIC_RIGHT => true,
				DefaultPresenter::IPINFO_VIEW_FULL_RIGHT => false,
			],
			'ipinfo-viewer' => [
				'ipinfo' => true,
				DefaultPresenter::IPINFO_VIEW_BASIC_RIGHT => true,
				DefaultPresenter::IPINFO_VIEW_FULL_RIGHT => true
			],
		] );
	}

	protected function newSpecialPage(): ?SpecialIPInfo {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'IPInfo' );
	}

	public function addDBDataOnce(): void {
		// NOTE: IPs used in this test have ASN and organization data in the mock GeoIP2 database
		// stored under fixtures/.
		// See https://github.com/maxmind/MaxMind-DB/blob/20cd0993da581394a34266da2a795434126d2c8d/source-data/GeoLite2-ASN-Test.json
		$req = new FauxRequest();
		$req->setIP( '214.78.120.5' );

		RequestContext::getMain()->setRequest( $req );

		$page = $this->getNonexistingTestPage();
		$otherPage = $this->getNonexistingTestPage();

		self::$tempUserWithEdits = $this->getServiceContainer()
			->getTempUserCreator()
			->create( null, $req )
			->getUser();

		$this->editPage( $page, 'test', '', NS_MAIN, self::$tempUserWithEdits );
		$req->setIP( '38.108.80.28' );
		$this->editPage( $page, 'test2', '', NS_MAIN, self::$tempUserWithEdits );

		$this->editPage( $otherPage, 'test3', '', NS_MAIN, self::$tempUserWithEdits );
	}

	public function testShouldRejectUserWithoutIPInfoPermission(): void {
		$this->expectException( PermissionsError::class );

		$performer = $this->getTestUser()->getAuthority();

		$this->executeSpecialPage(
			'',
			new FauxRequest(),
			'qqx',
			$performer
		);
	}

	public function testShouldRejectBlockedUser(): void {
		$this->expectException( UserBlockedError::class );

		$performer = $this->getMutableTestUser( 'sysop' )->getAuthority();

		$this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$performer->getUser(),
				$this->getTestSysop()->getAuthority(),
				'infinity'
			)
			->placeBlock();

		$this->executeSpecialPage(
			'',
			new FauxRequest(),
			'qqx',
			$performer
		);
	}

	public function testShouldReturnNoResultsForTemporaryUserWithoutContributions(): void {
		$req = new FauxRequest();

		$tempUser = $this->getServiceContainer()
			->getTempUserCreator()
			->create( null, $req )
			->getUser();

		$performer = $this->getTestSysop()->getAuthority();

		$this->getServiceContainer()
			->getUserOptionsManager()
			->setOption( $performer->getUser(), 'ipinfo-use-agreement', '1' );

		[ $html ] = $this->executeSpecialPage(
			$tempUser->getName(),
			new FauxRequest(),
			'qqx',
			$performer
		);

		$doc = self::parseHtml( $html );

		$this->assertNull( DOMCompat::querySelector( $doc, '.ext-ipinfo-special-ipinfo__table' ) );
		$this->assertCount(
			0,
			DOMCompat::querySelectorAll( $doc, '.ext-ipinfo-special-ipinfo__table > tbody > tr' )
		);
		$this->assertSame(
			"(ipinfo-special-ipinfo-no-results: {$tempUser->getName()})",
			DOMCompat::querySelector( $doc, '.ext-ipinfo-special-ipinfo__zero-state' )->textContent
		);
	}

	public function testShouldShowFormToAcceptAgreementIfNotAcceptedYet(): void {
		$performer = $this->getTestSysop()->getAuthority();

		[ $html ] = $this->executeSpecialPage(
			self::$tempUserWithEdits->getUser()->getName(),
			new FauxRequest(),
			'qqx',
			$performer
		);

		$doc = self::parseHtml( $html );

		$this->assertNull( DOMCompat::querySelector( $doc, '.ext-ipinfo-special-ipinfo__table' ) );
		$this->assertNotNull( DOMCompat::querySelector( $doc, 'input[name=wpAcceptAgreement]' ) );
	}

	public function testShouldShowResultsForTemporaryUserWithContributionsIfAgreementAcceptedOnSubmit(): void {
		$performer = $this->getTestSysop()->getAuthority();
		$req = new FauxRequest(
			[
				'wpTarget' => self::$tempUserWithEdits->getUser()->getName(),
				'wpAcceptAgreement' => true
			],
			true
		);

		[ $html ] = $this->executeSpecialPage(
			null,
			$req,
			'qqx',
			$performer
		);

		$postSubmitPref = $this->getServiceContainer()
			->getUserOptionsLookup()
			->getBoolOption( $performer->getUser(), 'ipinfo-use-agreement' );

		$doc = self::parseHtml( $html );

		$this->assertTrue( $postSubmitPref );
		$this->assertNotNull( DOMCompat::querySelector( $doc, '.ext-ipinfo-special-ipinfo__table' ) );
		$this->assertCount(
			2,
			DOMCompat::querySelectorAll( $doc, '.ext-ipinfo-special-ipinfo__table > tbody > tr' )
		);
	}

	public function testShouldShowResultsForTemporaryUserWithContributionsIfAgreementAlreadyAccepted(): void {
		$performer = $this->getTestSysop()->getAuthority();

		$this->getServiceContainer()
			->getUserOptionsManager()
			->setOption( $performer->getUser(), 'ipinfo-use-agreement', '1' );

		[ $html ] = $this->executeSpecialPage(
			self::$tempUserWithEdits->getUser()->getName(),
			new FauxRequest(),
			'qqx',
			$performer
		);

		$doc = self::parseHtml( $html );

		$this->assertNotNull( DOMCompat::querySelector( $doc, '.ext-ipinfo-special-ipinfo__table' ) );
		$this->assertCount(
			2,
			DOMCompat::querySelectorAll( $doc, '.ext-ipinfo-special-ipinfo__table > tbody > tr' )
		);
		$this->assertSame(
			[ '', '' ],
			self::getAsns( $doc )
		);
		$this->assertSame(
			[ '', '' ],
			self::getOrganizations( $doc )
		);
	}

	/**
	 * @dataProvider provideResults
	 */
	public function testShouldShowResultsForPerformerWithExtendedPermissions(
		array $requestParams,
		array $expectedAsns,
		array $expectedOrganizations
	): void {
		$performer = $this->getMutableTestUser( [ 'ipinfo-viewer' ] )->getAuthority();

		$this->getServiceContainer()
			->getUserOptionsManager()
			->setOption( $performer->getUser(), 'ipinfo-use-agreement', '1' );

		$req = new FauxRequest( $requestParams );

		[ $html ] = $this->executeSpecialPage(
			self::$tempUserWithEdits->getUser()->getName(),
			$req,
			'qqx',
			$performer
		);

		$doc = self::parseHtml( $html );

		$this->assertNotNull( DOMCompat::querySelector( $doc, '.ext-ipinfo-special-ipinfo__table' ) );
		$this->assertCount(
			2,
			DOMCompat::querySelectorAll( $doc, '.ext-ipinfo-special-ipinfo__table > tbody > tr' )
		);
		$this->assertSame( $expectedAsns, self::getAsns( $doc ) );
		$this->assertSame( $expectedOrganizations, self::getOrganizations( $doc ) );
	}

	public static function provideResults(): iterable {
		yield 'no sorting' => [
			[],
			[ '721', '174' ],
			[ 'DoD Network Information Center', 'Cogent Communications' ]
		];
		yield 'sorted by organization, ascending' => [
			[
				'wpSortDirection' => 'asc',
				'wpSortField' => 'organization'
			],
			[ '174', '721' ],
			[ 'Cogent Communications', 'DoD Network Information Center' ]
		];
	}

	/**
	 * Convenience function to retrieve the ASN stored in the table.
	 * @param DOMDocument $doc The DOM document to retrieve data from.
	 * @return string[] Contents of the table cells holding the ASN.
	 */
	private static function getAsns( DOMDocument $doc ): array {
		$nodes = (array)DOMCompat::querySelectorAll(
			$doc,
			".ext-ipinfo-special-ipinfo__table > tbody > tr > td:nth-of-type(4)"
		);

		return array_map( fn ( DOMNode $node ) => $node->textContent, $nodes );
	}

	/**
	 * Convenience function to retrieve the organization names stored in the table.
	 * @param DOMDocument $doc The DOM document to retrieve data from.
	 * @return string[] Contents of the table cells holding the organization name.
	 */
	private static function getOrganizations( DOMDocument $doc ): array {
		$nodes = (array)DOMCompat::querySelectorAll(
			$doc,
			".ext-ipinfo-special-ipinfo__table > tbody > tr > td:nth-of-type(5)"
		);

		return array_map( fn ( DOMNode $node ) => $node->textContent, $nodes );
	}

	/**
	 * @dataProvider provideInvalidUsers
	 */
	public function testShouldRejectInvalidUserArgument(
		Closure $targetNameProvider,
		string $errorMessageKey
	): void {
		$performer = $this->getTestSysop()->getAuthority();

		$this->getServiceContainer()
			->getUserOptionsManager()
			->setOption( $performer->getUser(), 'ipinfo-use-agreement', '1' );

		$targetNameProvider = $targetNameProvider->bindTo( $this );
		$userName = $targetNameProvider();
		[ $html ] = $this->executeSpecialPage(
			$userName,
			new FauxRequest(),
			'qqx',
			$performer
		);

		$doc = self::parseHtml( $html );
		$errors = (array)DOMCompat::querySelectorAll( $doc, '[role=alert]' );

		$this->assertNull( DOMCompat::querySelector( $doc, '.ext-ipinfo-special-ipinfo__table' ) );
		$this->assertSame( "($errorMessageKey: $userName)", $errors[1]->textContent );
	}

	public static function provideInvalidUsers(): iterable {
		// phpcs:disable Squiz.Scope.StaticThisUsage.Found
		yield 'named user' => [
			fn (): string => $this->getTestUser()->getUserIdentity()->getName(),
			'htmlform-user-not-valid'
		];

		yield 'anonymous user' => [
			fn (): string => '127.0.0.1',
			'htmlform-user-not-valid'
		];

		yield 'non-existent temporary user' => [
			function (): string {
				$req = new FauxRequest();
				return $this->getServiceContainer()
					->getTempUserCreator()
					->acquireAndStashName( $req->getSession() );
			},
			'htmlform-user-not-exists'
		];
		// phpcs:enable
	}

	/**
	 * Convenience function to parse an HTML fragment into a full PHP DOMDocument instance.
	 * @param string $html
	 * @return DOMDocument
	 */
	private static function parseHtml( string $html ): DOMDocument {
		$html = HtmlFormatter::wrapHTML( $html );
		return ( new HtmlFormatter( $html ) )->getDoc();
	}
}
