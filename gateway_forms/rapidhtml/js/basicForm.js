
$( document ).ready( function () {

	// check for RapidHtml errors and display, if any
	var amountErrorString = "";
	var billingErrorString = "";
	var paymentErrorString = "";

	// lookup formatted errors to display
	var searchVars = [ amountErrors, billingErrors, paymentErrors ];
	var errorMessages = [];
	for ( var errorFields in searchVars ) {
		for ( var field in errorFields ) {
			if ( errorFields[field] ) {
				errorMessages.push( errorFields[field] );
			}
		}
	}
	errorString = errorMessages.join( "<br />" );

	if ( errorString ) {
		$( "#topError" ).html( errorString );
	}

	$( "#paymentContinueBtn" ).click( function() {
		// FIXME: generalize validation
		if ( validateAmount() ) {
			document.payment.action = actionURL;
			document.payment.submit();
		}
	} );

} );

