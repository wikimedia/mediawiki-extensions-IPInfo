( function () {
	require( './widget.js' );
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Contributions' ) {
		require( './infoBox/widget.js' );
		require( './infoBox/init.js' );
	} else {
		require( './popup/widget.js' );
		require( './popup/init.js' );
	}
}() );
