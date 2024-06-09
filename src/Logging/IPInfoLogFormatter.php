<?php

namespace MediaWiki\IPInfo\Logging;

use LogEntry;
use LogFormatter;
use MediaWiki\Linker\Linker;
use MediaWiki\Message\Message;
use MediaWiki\User\UserFactory;

class IPInfoLogFormatter extends LogFormatter {

	private UserFactory $userFactory;

	public function __construct(
		LogEntry $entry,
		UserFactory $userFactory
	) {
		parent::__construct( $entry );
		$this->userFactory = $userFactory;
	}

	/** @inheritDoc */
	protected function getMessageParameters() {
		$params = parent::getMessageParameters();

		// Update the log line depending on if the user had their access enabled or disabled
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
			// Replace an IP user page link with IP contributions page link.
			// Don't use the LogFormatter::makeUserLink function, because that adds tools links.
			$ip = $this->entry->getTarget()->getText();
			$params[2] = Message::rawParam(
				Linker::userLink( 0, $this->userFactory->newAnonymous( $ip ) )
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
