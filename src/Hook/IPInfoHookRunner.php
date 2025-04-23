<?php

namespace MediaWiki\IPInfo\Hook;

use MediaWiki\HookContainer\HookContainer;

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
	public function onIPInfoHandlerRun( string $target, string $dataContext, array &$dataContainer ) {
		$this->hookContainer->run(
			'IPInfoHandlerRun',
			[ $target, $dataContext, &$dataContainer ]
		);
	}
}
