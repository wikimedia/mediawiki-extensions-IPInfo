<?php

namespace MediaWiki\IPInfo\Hook;

use MediaWiki\Permissions\Authority;

interface IPInfoIPInfoHandlerHook {
	/**
	 * Allows other extensions to add their data to the IPInfo data presenter.
	 * This is useful if extensions want to add their own data values to display
	 * via the javasript widget.
	 *
	 * The handler expects data returned with the following signature:
	 * [ source => [ key => value ] ]
	 *
	 * @param string $target user being looked up
	 * @param Authority $performer user performing the action
	 * @param string $dataContext either 'infobox' or 'popup'
	 * @param array &$dataContainer array to add new data to
	 * @return void
	 */
	public function onIPInfoHandlerRun(
		string $target,
		Authority $performer,
		string $dataContext,
		array &$dataContainer
	);
}
