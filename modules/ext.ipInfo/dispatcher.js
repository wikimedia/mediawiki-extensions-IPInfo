if (
	mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Contributions' ||
	mw.config.get( 'wgCanonicalSpecialPageName' ) === 'DeletedContributions'
) {
	require( './infobox/init.js' );
} else {
	require( './popup/init.js' );
}
