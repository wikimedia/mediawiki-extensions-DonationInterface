
$( document ).ready( function () {

	// check for RapidHtml errors and display, if any
	var amountErrorString = "";
	var billingErrorString = "";
	var paymentErrorString = "";

	// generate formatted errors to display
	var temp = [];
	for ( var e in amountErrors )
		if ( amountErrors[e] != "" )
			temp[temp.length] = amountErrors[e];
	amountErrorString = temp.join( "<br />" );

	temp = [];
	for ( var f in billingErrors )
		if ( billingErrors[f] != "" )
			temp[temp.length] = billingErrors[f];
	billingErrorString = temp.join( "<br />" );

	temp = [];
	for ( var g in paymentErrors )
		if ( paymentErrors[g] != "" )
			temp[temp.length] = paymentErrors[g];
	paymentErrorString = temp.join( "<br />" );

	// show the errors
	if ( amountErrorString != "" ) {
		$( "#topError" ).html( amountErrorString );
	} else if ( billingErrorString != "" ) {
		$( "#topError" ).html( billingErrorString );
	} else if ( paymentErrorString != "" ) {
		$( "#topError" ).html( paymentErrorString );
	}

	$( "#bt-continueBtn" ).live( "click", function() {
		if ( validateAmount() ) { //&& validate_personal( document.paypalcontribution ) ) {
			document.paypalcontribution.action = actionURL;
			document.paypalcontribution.submit();
		}
	} );

} );

