if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Contributions' ) {
	require( './infobox/init.js' );
} else {
	require( './popup/init.js' );
}
