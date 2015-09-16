$( document ).ready( function() {
	var form = $( '#payment-form' )[0];
	// If a submethod is already selected on page load, show the continue button
	if ( $( 'input[name="payment_submethod"]:checked' ).length > 0 ) {
		$( '#paymentContinue' ).show();
	}

	// Submit on submethod selection if valid, otherwise show continute button.
	$( 'input[name="payment_submethod"]' ).on( 'change', function() {
		if ( window.validate_form( form ) ) {
			submitForm();
		} else {
			$( '#paymentContinue' ).show();
		}
	} );

	$( '#paymentContinueBtn' ).click( function() {
		if ( window.validate_form( form ) ) {
			submitForm();
		}
	});

	function submitForm() {
		$( '#overlay' ).show();
		form.submit();
	}
});
