// TODO: move to a forms/js directory.
$( document ).ready( function() {
	var form = $( '#payment-form' )[0];
	// If a submethod is already selected on page load, show the continue button
	if ( $( 'input[name="payment_submethod"]:checked' ).length > 0 ) {
		$( '#paymentContinue' ).show();
	}

	// Submit on submethod click if valid,
	// otherwise show continue button.
	$( 'input[name="payment_submethod"]' ).on( 'click', function() {
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

	// Magic to hopefully disable the spinner in case we are returnd to this
	// page via the Back button.
	$( window ).on( 'unload', function() {
		$( '#overlay' ).hide();
	} );
});
