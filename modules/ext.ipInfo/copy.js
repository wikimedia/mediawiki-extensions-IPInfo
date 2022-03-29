var eventLogger = require( '../log.js' );

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
			eventLogger.log( 'copy', 'ip_address' );
		}
	} );
};

module.exports = logIpCopy;
