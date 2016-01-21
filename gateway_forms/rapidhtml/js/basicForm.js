/*global amountErrors:true, billingErrors:true, paymentErrors:true, actionURL:true*/
$( document ).ready( function () {

	// check for RapidHtml errors and display, if any
	var temp, e, f, g,
		amountErrorString = '',
		billingErrorString = '',
		paymentErrorString = '';

	// generate formatted errors to display
	temp = [];
	for ( e in amountErrors ) {
		if ( amountErrors[ e ] !== '' ) {
			temp[ temp.length ] = amountErrors[ e ];
		}
	}
	amountErrorString = temp.join( '<br />' );

	temp = [];
	for ( f in billingErrors ) {
		if ( billingErrors[ f ] !== '' ) {
			temp[ temp.length ] = billingErrors[ f ];
		}
	}
	billingErrorString = temp.join( '<br />' );

	temp = [];
	for ( g in paymentErrors ) {
		if ( paymentErrors[ g ] !== '' ) {
			temp[ temp.length ] = paymentErrors[ g ];
		}
	}
	paymentErrorString = temp.join( '<br />' );

	// show the errors
	if ( amountErrorString !== '' ) {
		$( '#topError' ).html( amountErrorString );
	} else if ( billingErrorString !== '' ) {
		$( '#topError' ).html( billingErrorString );
	} else if ( paymentErrorString !== '' ) {
		$( '#topError' ).html( paymentErrorString );
	}

	$( '#paymentContinueBtn' ).click( function () {
		if ( window.validateAmount() ) {
			document.payment.action = actionURL;
			document.payment.submit();
		}
	} );

} );
