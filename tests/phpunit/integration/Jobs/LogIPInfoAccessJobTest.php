<?php

namespace MediaWiki\IPInfo\Test\Integration\Jobs;

use MediaWiki\IPInfo\Jobs\LogIPInfoAccessJob;
use MediaWikiIntegrationTestCase;

/**
 * @group IPInfo
 * @covers \MediaWiki\IPInfo\Jobs\LogIPInfoAccessJob
 */
class LogIPInfoAccessJobTest extends MediaWikiIntegrationTestCase {
	public function provideDataContext() {
		return [ [ 'infobox' ], [ 'popup' ] ];
	}

	/**
	 * @dataProvider provideDataContext
	 */
	public function testValid( $dataContext ) {
		$job = new LogIPInfoAccessJob( null, [
			'performer' => $this->getTestUser()->getUser()->getName(),
			'ip' => '127.0.0.1',
			'dataContext' => $dataContext,
			'timestamp' => (int)wfTimestamp(),
			'access_level' => 'ipinfo-view-basic',
		] );

		$result = $job->run();
		$this->assertTrue( $result );
	}

	/**
	 * @dataProvider provideDataContext
	 */
	public function testInvalidPerformer( $dataContext ) {
		$job = new LogIPInfoAccessJob( null, [
			'performer' => 'Fake User',
			'ip' => '127.0.0.1',
			'dataContext' => $dataContext,
			'timestamp' => (int)wfTimestamp(),
			'access_level' => 'ipinfo-view-basic',
		] );

		$result = $job->run();
		$this->assertFalse( $result );
		$this->assertSame( 'Invalid performer', $job->getLastError() );
	}

	public function testInvalidDataContext() {
		$dataContext = 'foo';
		$job = new LogIPInfoAccessJob( null, [
			'performer' => $this->getTestUser()->getUser()->getName(),
			'ip' => '127.0.0.1',
			'dataContext' => $dataContext,
			'timestamp' => (int)wfTimestamp(),
			'access_level' => 'ipinfo-view-basic',
		] );

		$result = $job->run();
		$this->assertFalse( $result );
		$this->assertSame( "Invalid dataContext: $dataContext", $job->getLastError() );
	}
}
