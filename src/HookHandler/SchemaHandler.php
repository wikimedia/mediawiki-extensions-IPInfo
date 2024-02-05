<?php

namespace MediaWiki\IPInfo\HookHandler;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaHandler implements LoadExtensionSchemaUpdatesHook {
	/**
	 * @inheritDoc
	 * @codeCoverageIgnore This is tested by installing or updating MediaWiki
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->dropExtensionTable( 'ipinfo_ip_changes' );
	}
}
