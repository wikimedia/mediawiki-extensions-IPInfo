function postToRestApi( type, id, dataContext, retryOnTokenMismatch ) {
	if ( retryOnTokenMismatch === undefined ) {
		retryOnTokenMismatch = true;
	}
	var restApi = new mw.Rest();
	var api = new mw.Api();
	var deferred = $.Deferred();
	api.getToken( 'csrf' ).then( function ( token ) {
		restApi.post(
			'/ipinfo/v0/' +
			type + '/' + id +
			'?dataContext=' + dataContext +
			'&language=' + mw.config.values.wgUserLanguage,
			{ token: token }
		).then(
			function ( data ) {
				deferred.resolve( data );
			},
			function ( err, errObject ) {
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
						function ( data ) {
							deferred.resolve( data );
						},
						function ( secondRequestErr, secondRequestErrObject ) {
							deferred.reject( secondRequestErr, secondRequestErrObject );
						}
					);
				} else {
					deferred.reject( err, errObject );
				}
			}
		);
	} ).fail( function ( err, errObject ) {
		deferred.reject( err, errObject );
	} );
	return deferred.promise();
}

module.exports = postToRestApi;
