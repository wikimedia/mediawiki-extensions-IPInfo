<?php

namespace MediaWiki\IPInfo\Test\Integration\InfoRetriever;

use MediaWiki\IPInfo\InfoRetriever\ContributionInfoRetriever;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use User;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group IPInfo
 * @group Database
 * @covers \MediaWiki\IPInfo\InfoRetriever\ContributionInfoRetriever
 */
class ContributionInfoRetrieverIntegrationTest extends MediaWikiIntegrationTestCase {
	use TempUserTestTrait;

	private function getContributionInfoRetriever(): ContributionInfoRetriever {
		return $this->getServiceContainer()->getService( 'IPInfoContributionInfoRetriever' );
	}

	/**
	 * Convert the given UserIdentity into a full User, creating it if it's a temporary user.
	 *
	 * @param UserIdentity $user
	 * @return User
	 */
	private function setupUser( UserIdentity $user ): User {
		if ( !$user->isRegistered() ) {
			$this->disableAutoCreateTempUser();
			return $this->getServiceContainer()->getUserFactory()->newFromUserIdentity( $user );
		}

		return $this->getServiceContainer()
			->getTempUserCreator()
			->create( $user->getName(), new FauxRequest() )
			->getUser();
	}

	public function testAllValuesShouldBeZeroForNonexistentTempUser(): void {
		$req = new FauxRequest();
		$name = $this->getServiceContainer()
			->getTempUserCreator()
			->acquireAndStashName( $req->getSession() );
		$user = $this->getServiceContainer()->getUserFactory()->newFromName( $name );

		$info = $this->getContributionInfoRetriever()->retrieveFor( $user );

		$this->assertSame( 0, $info->getNumLocalEdits() );
		$this->assertSame( 0, $info->getNumRecentEdits() );
		$this->assertSame( 0, $info->getNumDeletedEdits() );
	}

	/**
	 * @dataProvider provideUsers
	 */
	public function testAllValuesShouldBeZeroForUserWithoutContributions( UserIdentity $user ): void {
		$user = $this->setupUser( $user );
		$info = $this->getContributionInfoRetriever()->retrieveFor( $user );

		$this->assertSame( 0, $info->getNumLocalEdits() );
		$this->assertSame( 0, $info->getNumRecentEdits() );
		$this->assertSame( 0, $info->getNumDeletedEdits() );
	}

	/**
	 * @dataProvider provideUsers
	 */
	public function testShouldReturnContributionsForUser( UserIdentity $user ): void {
		$user = $this->setupUser( $user );

		$page = $this->getNonexistingTestPage();
		$otherPage = $this->getNonexistingTestPage();

		$this->editPage( $page, 'test', '', NS_MAIN, $user );
		$this->editPage( $page, 'test2', '', NS_MAIN, $user );
		$this->editPage( $otherPage, 'test3', '', NS_MAIN, $user );

		$this->deletePage( $otherPage );

		$info = $this->getContributionInfoRetriever()->retrieveFor( $user );

		$this->assertSame( 2, $info->getNumLocalEdits() );
		$this->assertSame( 2, $info->getNumRecentEdits() );
		$this->assertSame( 1, $info->getNumDeletedEdits() );
	}

	/**
	 * @dataProvider provideUsers
	 */
	public function testShouldIgnoreContributionsOlderThanADay( UserIdentity $user ): void {
		$user = $this->setupUser( $user );

		$page = $this->getNonexistingTestPage();

		$twoDaysAgo = wfTimestamp() - ( 48 * 60 * 60 );

		ConvertibleTimestamp::setFakeTime( $twoDaysAgo );

		$this->editPage( $page, 'test', '', NS_MAIN, $user );
		$this->editPage( $page, 'test2', '', NS_MAIN, $user );

		ConvertibleTimestamp::setFakeTime( false );

		$this->editPage( $page, 'test3', '', NS_MAIN, $user );

		$info = $this->getContributionInfoRetriever()->retrieveFor( $user );

		$this->assertSame( 3, $info->getNumLocalEdits() );
		$this->assertSame( 1, $info->getNumRecentEdits() );
		$this->assertSame( 0, $info->getNumDeletedEdits() );
	}

	public static function provideUsers(): iterable {
		yield 'anonymous user' => [ new UserIdentityValue( 0, '127.0.0.3' ) ];
		yield 'temporary user' => [ new UserIdentityValue( 6, '~2024-8' ) ];
	}

	public function testShouldHaveProperName(): void {
		$this->assertSame( 'ipinfo-source-contributions', $this->getContributionInfoRetriever()->getName() );
	}
}
