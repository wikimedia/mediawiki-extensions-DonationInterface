( function ( $ ) {
	$( function () {
		var $employerRow = $( '#employer' ).closest( 'div' ),
			message = 'We\'ll let you know if your employer will match your gift';

		$employerRow.after( '<div>' + message + '</div>' );
	} );
} )( jQuery );
