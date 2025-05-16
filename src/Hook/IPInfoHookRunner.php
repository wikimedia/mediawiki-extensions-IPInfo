<?php

namespace MediaWiki\IPInfo\Hook;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Permissions\Authority;

class IPInfoHookRunner implements
	IPInfoIPInfoHandlerHook
{
	public const SERVICE_NAME = 'IPInfoHookRunner';

	private HookContainer $hookContainer;

	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @inheritDoc
	 */
	public function onIPInfoHandlerRun(
		string $target,
		Authority $performer,
		string $dataContext,
		array &$dataContainer
	) {
		$this->hookContainer->run(
			'IPInfoHandlerRun',
			[ $target, $performer, $dataContext, &$dataContainer ]
		);
	}
}
