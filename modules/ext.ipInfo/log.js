var log = function ( action, context ) {
	var specialPage = mw.config.get( 'wgCanonicalSpecialPageName' );
	switch ( specialPage ) {
		case 'Log':
			specialPage = 'special_log';
			break;
		case 'Recentchanges':
			specialPage = 'special_recentchanges';
			break;
		case 'Contributions':
			specialPage = 'special_contributions';
			break;
		case 'Watchlist':
			specialPage = 'special_watchlist';
			break;
		case false:
			specialPage = 'action_history';
			break;
	}

	var event = {
		/* eslint-disable camelcase */
		$schema: '/analytics/mediawiki/ipinfo_interaction/1.3.0',
		event_action: action,
		event_context: context,
		event_source: specialPage,
		user_edit_bucket: mw.config.get( 'wgUserEditCountBucket' ),
		user_groups: mw.config.get( 'wgUserGroups', [] ).join( '|' )
		/* eslint-enable camelcase */
	};

	if ( action === 'open_popup' ) {
		mw.user.getRights( function ( rights ) {
			var highestAccessLevel;

			if ( rights.indexOf( 'ipinfo-view-full' ) !== -1 ) {
				highestAccessLevel = 'full';
			} else if ( rights.indexOf( 'ipinfo-view-basic' ) !== -1 ) {
				highestAccessLevel = 'basic';
			}

			if ( highestAccessLevel ) {
				/* eslint-disable camelcase */
				event.event_ipinfo_version = highestAccessLevel;
				/* eslint-enable camelcase */
				mw.track( 'ipinfo.event', event );
			}
		} );
	} else {
		mw.track( 'ipinfo.event', event );
	}

};

var logIpCopy = function () {
	// Some IP addresses are text nodes in #firstHeading (see Special:Contributions) and others
	// are a.mw-anonuserlink elements (see Special:RecentChanges, for example). In order to
	// capture as many edge cases as possible, filter all copy events to see whether the user
	// copied just an IP address.
	document.addEventListener( 'copy', function () {
		var selection = document.getSelection().toString();

		// Filter out selections that are too long, since mw.util.isIPAddress() is costly. In
		// theory the longest IP address validated by mw.util.isAddress() is 39 characters, but
		// account for extra whitespace around the selection and for CIDR notation, in case we
		// decide to include ranges in the future.
		//
		// (An IPv4-mapped IPv6 address is 45 characters. An IPv6 address with a zone index _could_
		// be longer but it is unlikely. mw.util.isIPv6Address() does not validate either.)
		if ( selection.length < 50 && mw.util.isIPAddress( selection.trim() ) ) {
			log( 'copy', 'ip_address' );
		}
	} );
};

// require only runs this the first time, so this won't be run on subsequent requires
mw.trackSubscribe( 'ipinfo.event', function ( topic, eventData ) {
	if ( mw.eventLog ) {
		mw.eventLog.submit( 'mediawiki.ipinfo_interaction', eventData );
	}
} );

module.exports = {
	log: log,
	logIpCopy: logIpCopy
};
