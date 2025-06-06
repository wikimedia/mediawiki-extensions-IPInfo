<?php

namespace MediaWiki\IPInfo\Test\Unit\Logging;

use LogFormatterTestCase;
use MediaWiki\IPInfo\Logging\Logger;
use MediaWiki\IPInfo\Rest\Presenter\DefaultPresenter;

/**
 * @covers \MediaWiki\IPInfo\Logging\IPInfoLogFormatter
 * @group Database For checking if temp accounts are expired and style them as such
 */
class IPInfoLogFormatterTest extends LogFormatterTestCase {
	public static function provideIPInfoLogDatabaseRows(): array {
		return [
			'View infobox, full access' => [
				'row' => [
					'type' => 'ipinfo',
					'action' => Logger::ACTION_VIEW_INFOBOX,
					'user_text' => 'Sysop',
					'title' => '127.0.0.1',
					'params' => [
						'4::level' => DefaultPresenter::IPINFO_VIEW_FULL_RIGHT,
					],
				],
				'extra' => [
					'text' => 'Sysop viewed IP Information infobox for 127.0.0.1. Full access.',
					'api' => [
						'level' => DefaultPresenter::IPINFO_VIEW_FULL_RIGHT,
					],
				],
			],
			'View infobox for temp user, full access' => [
				'row' => [
					'type' => 'ipinfo',
					'action' => Logger::ACTION_VIEW_INFOBOX,
					'user_text' => 'Sysop',
					'title' => '~2024-8',
					'params' => [
						'4::level' => DefaultPresenter::IPINFO_VIEW_FULL_RIGHT,
					],
				],
				'extra' => [
					'text' => 'Sysop viewed IP Information infobox for ~2024-8. Full access.',
					'api' => [
						'level' => DefaultPresenter::IPINFO_VIEW_FULL_RIGHT,
					],
				],
			],
			'View popup, basic access' => [
				'row' => [
					'type' => 'ipinfo',
					'action' => Logger::ACTION_VIEW_POPUP,
					'user_text' => 'Sysop',
					'title' => '127.0.0.1',
					'params' => [
						'4::level' => DefaultPresenter::IPINFO_VIEW_BASIC_RIGHT,
					],
				],
				'extra' => [
					'text' => 'Sysop viewed IP Information popup for 127.0.0.1. Limited access.',
					'api' => [
						'level' => DefaultPresenter::IPINFO_VIEW_BASIC_RIGHT,
					],
				],
			],
			'Enable access' => [
				'row' => [
					'type' => 'ipinfo',
					'action' => Logger::ACTION_CHANGE_ACCESS,
					'user_text' => 'Sysop',
					'params' => [
						'4::changeType' => Logger::ACTION_ACCESS_ENABLED,
					],
				],
				'extra' => [
					'text' => 'Sysop enabled their own access to IP Information',
					'api' => [
						'changeType' => Logger::ACTION_ACCESS_ENABLED,
					],
				],
			],
			'Disable access' => [
				'row' => [
					'type' => 'ipinfo',
					'action' => Logger::ACTION_CHANGE_ACCESS,
					'user_text' => 'Sysop',
					'params' => [
						'4::changeType' => Logger::ACTION_ACCESS_DISABLED,
					],
				],
				'extra' => [
					'text' => 'Sysop disabled their own access to IP Information',
					'api' => [
						'changeType' => Logger::ACTION_ACCESS_DISABLED,
					],
				],
			],
		];
	}

	/**
	 * @dataProvider provideIPInfoLogDatabaseRows
	 */
	public function testIPInfoLogDatabaseRows( $row, $extra ): void {
		$this->setGroupPermissions( 'sysop', 'ipinfo-view-log', true );
		$this->doTestLogFormatter( $row, $extra, 'sysop' );
	}
}
