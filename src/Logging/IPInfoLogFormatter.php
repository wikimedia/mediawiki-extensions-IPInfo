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

		return $params;
	}
}
