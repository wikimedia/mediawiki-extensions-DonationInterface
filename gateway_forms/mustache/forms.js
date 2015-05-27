$( document ).ready( function() {
	$( '#paymentContinueBtn' ).click( function() {
		if ( window.validateAmount() ) {
			$( '#payment-form' ).submit();
		}
	});
});
