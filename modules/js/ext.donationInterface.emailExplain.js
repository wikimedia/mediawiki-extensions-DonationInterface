( function ( $ ) {
	$( function () {
		var emailRow = $( '#email' ).closest( 'tr' ),
			message = 'We will email you a receipt to confirm your donation.';

		emailRow.after( '<tr><td>' + message + '</td></tr>' );
	} );
} )( jQuery );
