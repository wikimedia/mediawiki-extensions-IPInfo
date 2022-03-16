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
		case false:
			specialPage = 'action_history';
			break;
	}

	var event = {
		/* eslint-disable camelcase */
		$schema: '/analytics/mediawiki/ipinfo_interaction/1.0.0',
		event_action: action,
		event_context: context,
		event_source: specialPage,
		user_edit_bucket: mw.config.get( 'wgUserEditCountBucket' ),
		user_groups: mw.config.get( 'wgUserGroups', [] ).join( '|' )
		/* eslint-enable camelcase */
	};

	mw.track( 'ipinfo.event', event );
};

var init = function () {
	mw.trackSubscribe( 'ipinfo.event', function ( topic, eventData ) {
		if ( mw.eventLog ) {
			mw.eventLog.submit( 'mediawiki.ipinfo_interaction', eventData );
		}
	} );
};

module.exports = {
	init: init,
	log: log
};
