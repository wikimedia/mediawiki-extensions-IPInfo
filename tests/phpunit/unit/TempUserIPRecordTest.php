<?php
namespace MediaWiki\IPInfo\Test\Unit;

use MediaWiki\IPInfo\TempUserIPRecord;
use MediaWikiUnitTestCase;
use Wikimedia\Assert\PreconditionException;

/**
 * @covers \MediaWiki\IPInfo\TempUserIPRecord
 */
class TempUserIPRecordTest extends MediaWikiUnitTestCase {
	public function testShouldRequireEitherALogIdOrRevisionId(): void {
		$this->expectException( PreconditionException::class );
		$this->expectExceptionMessage(
			'Precondition failed: Either the $revisionId or the $logId parameter must be non-null'
		);

		new TempUserIPRecord( '127.0.0.1', null, null );
	}

	/**
	 * @dataProvider provideConstructParameters
	 */
	public function testConstruct( string $ip, ?int $revisionId, ?int $logId ): void {
		$record = new TempUserIPRecord( $ip, $revisionId, $logId );

		$this->assertSame( $ip, $record->getIp() );
		$this->assertSame( $revisionId, $record->getRevisionId() );
		$this->assertSame( $logId, $record->getLogId() );
	}

	public static function provideConstructParameters(): iterable {
		yield 'both revision and log IDs provided' => [
			'127.0.0.1',
			1,
			2
		];

		yield 'only log ID provided' => [
			'127.0.0.1',
			null,
			3
		];

		yield 'only revision ID provided' => [
			'127.0.0.1',
			4,
			null
		];
	}
}
