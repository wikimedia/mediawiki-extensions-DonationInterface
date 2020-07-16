( function ( $ ) {
	$( function () {
		var $panDiv = $( '#fiscal_number' ).closest( 'div' ),
			message = mw.msg( 'donate_interface-donor-fiscal_number-explain-in' );

		$panDiv.after( '<div>' + message + '</div>' );
	} );
} )( jQuery );
