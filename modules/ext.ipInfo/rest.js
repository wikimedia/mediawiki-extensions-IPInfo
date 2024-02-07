function postToRestApi( type, id, dataContext, retryOnTokenMismatch ) {
	if ( retryOnTokenMismatch === undefined ) {
		retryOnTokenMismatch = true;
	}
	var restApi = new mw.Rest();
	var api = new mw.Api();
	return api.getToken( 'csrf' ).then( function ( token ) {
		return restApi.post(
			'/ipinfo/v0/' +
			type + '/' + id +
			'?dataContext=' + dataContext +
			'&language=' + mw.config.values.wgUserLanguage,
			{ token: token }
		).fail( function ( _err, errObject ) {
			if (
				retryOnTokenMismatch &&
				errObject.xhr &&
				errObject.xhr.responseJSON &&
				errObject.xhr.responseJSON.errorKey &&
				errObject.xhr.responseJSON.errorKey === 'rest-badtoken'
			) {
				// The CSRF token has expired. Retry the POST with a new token.
				api.badToken( 'csrf' );
				return postToRestApi( type, id, dataContext, false );
			}
		} );
	} );
}

module.exports = postToRestApi;
