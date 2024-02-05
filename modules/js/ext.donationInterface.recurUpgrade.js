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
		$otherAmountField.change( setSubmitState );
		$otherAmountField.focus( function () {
			$amountField.filter( '[value=other]' ).prop( 'checked', true );
			setSubmitState();
		} );
		setSubmitState();
	} );
} )( jQuery );
