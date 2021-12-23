( function () {
	require( './widget.js' );
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Contributions' ) {
		require( './infobox/widget.js' );
		require( './infobox/init.js' );
	} else {
		require( './popup/widget.js' );
		require( './popup/init.js' );
	}
}() );
