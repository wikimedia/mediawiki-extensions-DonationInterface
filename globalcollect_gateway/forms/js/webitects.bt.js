
$( document ).ready( function () {

	// check for RapidHtml errors and display, if any
	var amountErrorString = "",
		billingErrorString = "",
		paymentErrorString = "";

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
	var prevError = false;
	if ( amountErrorString != "" ) {
		$( "#amtErrorMessages" ).html( amountErrorString );
	}
	if ( billingErrorString != "" ) {
		$( "#billingErrorMessages" ).html( billingErrorString );
		showAmount( $( 'input[name="amount"]' ) ); // lets go ahead and assume there is something to show
	}
	if ( paymentErrorString != "" ) {
		$( "#paymentErrorMessages" ).html( paymentErrorString );
		showAmount( $( 'input[name="amount"]' ) ); // lets go ahead and assume there is something to show
	}
	$( "#bt-continueBtn" ).live( "click", function() {
		if ( validate_personal( document.paypalcontribution ) ) {
			document.paypalcontribution.action = actionURL;
			document.paypalcontribution.submit();
		}
	} );
} );