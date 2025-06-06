<?php

namespace MediaWiki\IPInfo\Logging;

use MediaWiki\Linker\Linker;
use MediaWiki\Logging\LogFormatter;
use MediaWiki\Message\Message;

class IPInfoLogFormatter extends LogFormatter {

	/** @inheritDoc */
	protected function getMessageParameters() {
		$params = parent::getMessageParameters();

		// Update the log line depending on if the user had their access enabled or disabled
		if ( $this->entry->getSubtype() === 'change_access' ) {
			// Message keys used:
			// - 'ipinfo-change-access-level-enable'
			// - 'ipinfo-change-access-level-disable'
			// - 'ipinfo-change-access-level-enable-globally'
			// - 'ipinfo-change-access-level-disable-globally'
			$params[3] = $this->msg( 'ipinfo-change-access-level-' . $params[3], $params[1] );
		}

		if (
			$this->entry->getSubtype() === 'view_infobox' ||
			$this->entry->getSubtype() === 'view_popup'
		) {
			// Generate an appropriate user page or contributions page link.
			// Don't use the LogFormatter::makeUserLink function, because that adds tools links.
			$targetName = $this->entry->getTarget()->getText();
			$params[2] = Message::rawParam(
				Linker::userLink( 0, $targetName )
			);

			// Replace access level parameter with the message.
			// Message keys used:
			// - 'ipinfo-log-access-level-ipinfo-view-basic'
			// - 'ipinfo-log-access-level-ipinfo-view-full'
			$params[3] = $this->msg( 'ipinfo-log-access-level-' . $params[3] );
		}

		return $params;
	}
}
