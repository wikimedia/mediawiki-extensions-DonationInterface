( function ( $ ) {
	$( function () {
		var employerRow = $( '#employer' ).closest( 'tr' ),
			message = 'Your employer might match your gift, doubling the impact you have. <br />' +
				'If your employer is on our list of partners, we\'ll follow up with you.';

		employerRow.after( '<tr><td>' + message + '</td></tr>' );
	} );
} )( jQuery );
