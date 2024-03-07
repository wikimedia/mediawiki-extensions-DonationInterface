( function ( $ ) {
	$( function () {
		var $submitButton = $( '#save' ),
			$amountField = $( 'input[name=upgrade_amount]' ),
			$otherAmountField = $( 'input[name=upgrade_amount_other]' );
		function setSubmitState() {
			var valueIsValid = false, value = $amountField.filter( ':checked' ).val();
			if ( value === 'other' ) {
				valueIsValid = ( $otherAmountField.val() > 0 );
			} else if ( value > 0 ) {
				valueIsValid = true;
				$otherAmountField.val( '' );
			}
			if ( valueIsValid ) {
				$submitButton.removeClass( 'disabled' );
				$submitButton.prop( 'disabled', false );
			} else {
				$submitButton.addClass( 'disabled' );
				$submitButton.prop( 'disabled', true );
			}
		}

		$amountField.change( setSubmitState );
		// We need to use setInterval because the new value of the other amount
		// field is not immediately available in the keyup handler
		$otherAmountField.on( 'change keyup', function () {
			setInterval( setSubmitState, 0 );
		} );
		$otherAmountField.focus( function () {
			$amountField.filter( '[value=other]' ).prop( 'checked', true );
			setSubmitState();
		} );
		setSubmitState();
	} );
} )( jQuery );
