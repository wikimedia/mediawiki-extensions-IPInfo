<?php

namespace MediaWiki\IPInfo\Logging;

use Linker;
use LogFormatter;
use MediaWiki\MediaWikiServices;
use Message;

class IPInfoLogFormatter extends LogFormatter {
	/**
	 * @inheritDoc
	 */
	protected function getMessageParameters() {
		$params = parent::getMessageParameters();

		// Update the logline depending on if the user had their access enabled or disabled
		if ( $this->entry->getSubtype() === 'change_access' ) {
			// Message keys used:
			// - 'ipinfo-change-access-level-enable'
			// - 'ipinfo-change-access-level-disable'
			$params[3] = $this->msg( 'ipinfo-change-access-level-' . $params[3], $params[1] );
		}

		if (
			$this->entry->getSubtype() === 'view_infobox' ||
			$this->entry->getSubtype() === 'view_popup'
		) {
			// Replace IP user page link to IP contributions page link.
			// Don't use LogFormatter::makeUserLink, because that adds tools links.
			$ip = $this->entry->getTarget()->getText();
			$userFactory = MediaWikiServices::getInstance()->getUserFactory();
			$params[2] = Message::rawParam(
				Linker::userLink( 0, $userFactory->newAnonymous( $ip ) )
			);

			// Replace access level parameter with message
			// Message keys used:
			// - 'ipinfo-log-access-level-ipinfo-view-basic'
			// - 'ipinfo-log-access-level-ipinfo-view-full'
			$params[3] = $this->msg( 'ipinfo-log-access-level-' . $params[3] );
		}

		return $params;
	}
}
