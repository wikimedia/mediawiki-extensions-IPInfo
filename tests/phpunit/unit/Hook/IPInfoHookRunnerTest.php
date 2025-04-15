<?php

namespace MediaWiki\IPInfo\Test\Unit\Hook;

use MediaWiki\IPInfo\Hook\IPInfoHookRunner;
use MediaWiki\Tests\HookContainer\HookRunnerTestBase;

/**
 * @covers \MediaWiki\IPInfo\Hook\IPInfoHookRunner
 */
class IPInfoHookRunnerTest extends HookRunnerTestBase {

	public static function provideHookRunners() {
		yield IPInfoHookRunner::class => [ IPInfoHookRunner::class ];
	}
}
