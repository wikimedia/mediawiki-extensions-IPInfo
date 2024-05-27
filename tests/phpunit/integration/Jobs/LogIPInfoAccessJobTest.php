<?php

namespace MediaWiki\IPInfo\Test\Integration\Jobs;

use MediaWiki\IPInfo\Jobs\LogIPInfoAccessJob;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;
use MediaWikiIntegrationTestCase;

/**
 * @group IPInfo
 * @group Database
 * @covers \MediaWiki\IPInfo\Jobs\LogIPInfoAccessJob
 */
class LogIPInfoAccessJobTest extends MediaWikiIntegrationTestCase {
	public static function provideDataContext() {
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
			'access_level' => DefaultPresenter::IPINFO_VIEW_BASIC_RIGHT,
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
			'access_level' => DefaultPresenter::IPINFO_VIEW_BASIC_RIGHT,
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
			'access_level' => DefaultPresenter::IPINFO_VIEW_BASIC_RIGHT,
		] );

		$result = $job->run();
		$this->assertFalse( $result );
		$this->assertSame( "Invalid dataContext: $dataContext", $job->getLastError() );
	}
}
