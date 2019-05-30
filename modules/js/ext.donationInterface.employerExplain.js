( function ( $ ) {
	$( function () {
		var employerRow = $( '#employer' ).closest( 'tr' ),
			message = 'We\'ll let you know if your employer will match your gift';

		employerRow.after( '<tr><td>' + message + '</td></tr>' );
	} );
} )( jQuery );
