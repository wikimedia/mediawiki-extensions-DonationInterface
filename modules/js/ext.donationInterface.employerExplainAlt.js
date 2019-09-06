( function ( $ ) {
	$( function () {
		var employerRow = $( '#employer' ).closest( 'div' ),
			message = 'Your employer might match your gift, doubling the impact you can have. If your employer is on our list of partners, we\'ll follow up with you.';

		employerRow.after( '<div>' + message + '</div>' );
	} );
} )( jQuery );
