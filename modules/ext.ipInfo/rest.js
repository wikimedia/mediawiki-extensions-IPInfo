function postToRestApi( type, id, dataContext, retryOnTokenMismatch ) {
	if ( retryOnTokenMismatch === undefined ) {
		retryOnTokenMismatch = true;
	}
	const restApi = new mw.Rest();
	const api = new mw.Api();
	const deferred = $.Deferred();
	api.getToken( 'csrf' ).then( ( token ) => {
		restApi.post(
			'/ipinfo/v0/' +
			type + '/' + id +
			'?dataContext=' + dataContext +
			'&language=' + mw.config.values.wgUserLanguage,
			{ token: token }
		).then(
			( data ) => {
				deferred.resolve( data );
			},
			( _err, errObject ) => {
				if (
					retryOnTokenMismatch &&
					errObject.xhr &&
					errObject.xhr.responseJSON &&
					errObject.xhr.responseJSON.errorKey &&
					errObject.xhr.responseJSON.errorKey === 'rest-badtoken'
				) {
					// The CSRF token has expired. Retry the POST with a new token.
					api.badToken( 'csrf' );
					postToRestApi( type, id, dataContext, false ).then(
						( data ) => {
							deferred.resolve( data );
						},
						( secondRequestErr, secondRequestErrObject ) => {
							deferred.reject( secondRequestErr, secondRequestErrObject );
						}
					);
				} else {
					deferred.reject( errObject );
				}
			}
		);
	} ).fail( ( errObject ) => {
		deferred.reject( errObject );
	} );
	return deferred.promise();
}

module.exports = postToRestApi;
