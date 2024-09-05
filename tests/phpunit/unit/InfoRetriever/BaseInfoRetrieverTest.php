<?php
namespace MediaWiki\IPInfo\Test\Unit\InfoRetriever;

use MediaWiki\IPInfo\InfoRetriever\BaseInfoRetriever;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\IPInfo\InfoRetriever\BaseInfoRetriever
 */
class BaseInfoRetrieverTest extends MediaWikiUnitTestCase {
	public function testShouldOfferDefaultBatchImplementation(): void {
		$retriever = new class extends BaseInfoRetriever {
			public function getName(): string {
				return 'test';
			}

			public function retrieveFor( UserIdentity $user, ?string $ip ): string {
				return "some-info-for-$ip";
			}
		};
		$user = new UserIdentityValue( 1, 'TestUser' );
		$ips = [ '1.1.1.1', '2.2.2.2', '3.3.3.3' ];

		$infoMap = $retriever->retrieveBatch( $user, $ips );

		$this->assertCount( 3, $infoMap );

		foreach ( $ips as $ip ) {
			$this->assertSame( "some-info-for-$ip", $infoMap[$ip] );
		}
	}
}
