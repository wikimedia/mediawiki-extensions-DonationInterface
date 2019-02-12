( function ( $, mw ) {
	$( function () {
		var emailRow = $( '#email' ).closest( 'tr' ),
			message = mw.msg( 'donate_interface-email-explain' );

		emailRow.after( '<tr><td>' + message + '</td></tr>' );
	} );
} )( jQuery, mediaWiki );
