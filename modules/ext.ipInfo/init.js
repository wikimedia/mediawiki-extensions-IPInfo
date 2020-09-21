( function () {
	$( '.mw-anonuserlink' ).after( function () {
		var revId,
			$revIdAncestor = $( this ).closest( '[data-mw-revid]' ),
			button = new OO.ui.PopupButtonWidget( {
				icon: 'info',
				framed: false,
				classes: [ 'ext-ipinfo-button' ],
				popup: {
					padded: true
				}
			} );

		if ( $revIdAncestor !== undefined ) {
			revId = $revIdAncestor.data( 'mwRevid' );
		}

		if ( revId === undefined ) {
			return '';
		}

		button.once( 'click', function () {
			$.get(
				mw.config.get( 'wgScriptPath' ) +
				'/rest.php/ipinfo/v0/revision/' +
				revId
			).then( function ( data ) {
				var location = data.location.map( function ( item ) {
						return item.label;
					} ).join( mw.msg( 'comma-separator' ) ),
					source = mw.msg( 'ipinfo-popup-source-mock' );

				button.popup.$body.append(
					$( '<p>' ).addClass( 'ext-ipinfo-popup-location' ).text( location ),
					$( '<p>' ).addClass( 'ext-ipinfo-popup-source' ).text( source )
				);
			} );
		} );

		return button.$element;
	} );
}() );
