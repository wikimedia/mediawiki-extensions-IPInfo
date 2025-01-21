if (
	mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Contributions' ||
	mw.config.get( 'wgCanonicalSpecialPageName' ) === 'DeletedContributions' ||
	mw.config.get( 'wgCanonicalSpecialPageName' ) === 'IPContributions'
) {
	require( './infobox/init.js' );
} else {
	require( './popup/init.js' );
}
