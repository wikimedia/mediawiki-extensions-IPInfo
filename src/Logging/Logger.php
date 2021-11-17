<?php

namespace MediaWiki\IPInfo\Logging;

use MediaWiki\User\UserIdentity;

interface Logger {
	/**
	 * Represents a user (the performer) viewing information about an IP via the infobox.
	 *
	 * @var string
	 */
	public const ACTION_VIEW_ACCORDION = 'view_accordion';

	/**
	 * Represents a user (the performer) viewing information about an IP via the popup.
	 *
	 * @var string
	 */
	public const ACTION_VIEW_POPUP = 'view_popup';

	/**
	 * Logs the user (the performer) viewing information about an IP via the infobox.
	 *
	 * @param UserIdentity $performer
	 * @param string $ip
	 */
	public function logViewAccordion( UserIdentity $performer, string $ip ): void;

	/**
	 * Logs the user (the performer) viewing information about an IP via the popup.
	 *
	 * @param UserIdentity $performer
	 * @param string $ip
	 */
	public function logViewPopup( UserIdentity $performer, string $ip ): void;
}
