( function () {
	require( './widget.js' );
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Contributions' ) {
		require( './infoBox/init.js' );
	} else {
		require( './popup/init.js' );
	}
}() );
