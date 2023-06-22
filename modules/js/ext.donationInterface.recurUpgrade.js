( function ( $ ) {
	$( function () {
		var $submitButton = $( '#save' ),
			$amountField = $( 'input[name=upgrade_amount]' );

		$amountField.change( function () {
            var value = $amountField.filter( ':checked' ).val();
            if ( value ) {
                $submitButton.removeClass( 'disabled' );
                $submitButton.prop( 'disabled', false );
            } else {
                $submitButton.addClass( 'disabled' );
                $submitButton.prop( 'disabled', true );
            }
        } );
	} );
} )( jQuery );
