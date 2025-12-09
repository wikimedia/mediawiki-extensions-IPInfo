<?php

namespace MediaWiki\IPInfo\Hook;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Permissions\Authority;

class IPInfoHookRunner implements
	IPInfoIPInfoHandlerHook
{
	public function __construct( private readonly HookContainer $hookContainer ) {
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
