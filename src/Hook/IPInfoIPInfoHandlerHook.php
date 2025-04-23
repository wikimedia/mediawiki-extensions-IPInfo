<?php

namespace MediaWiki\IPInfo\Hook;

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
	 * @param string $dataContext either 'infobox' or 'popup'
	 * @param array &$dataContainer array to add new data to
	 * @return void
	 */
	public function onIPInfoHandlerRun(
		string $target,
		string $dataContext,
		array &$dataContainer
	);
}
