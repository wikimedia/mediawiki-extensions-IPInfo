<?php

namespace MediaWiki\IPInfo\HookHandler;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaHandler implements LoadExtensionSchemaUpdatesHook {
	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$extensionRoot = __DIR__ . '/../..';
		$engine = $updater->getDB()->getType();

		$updater->addExtensionTable( 'ipinfo_ip_changes', "$extensionRoot/sql/$engine/ipinfo_ip_changes.sql" );
	}
}
