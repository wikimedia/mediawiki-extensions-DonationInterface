
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
		if ( validateAmount() && validate_personal( document.paypalcontribution ) ) {
			document.paypalcontribution.action = actionURL;
			document.paypalcontribution.submit();
		}
	} );


	// check to see if amount was passed from the previous step
	var amount = $( 'input[name="amount"]' ); // get the amount field
	if( amount == null || isNaN( amount.val() ) || amount.val() <= 0 ){
		// the amount is not set
		$( "#step1wrapper" ).slideDown();
		$( "#selected-amount" ).html( '(' + $( 'input[name="currency_code"]' ).val() + ')' );

	} else {
		showAmount( $( 'input[name="amount"]' ) );
	}

	// Set selected amount to amount
	$( 'input[name="amountRadio"]' ).click( function() {
		setAmount( $( this ) );
	} );
	// reset the amount field when "other" is changed
	$( "#other-amount" ).change( function() {
		setAmount( $( this ) );
	} );

	$( "#step1header" ).click( function() {
		$( "#step1wrapper" ).slideDown();
		$( "#change-amount" ).hide();
	} );


	// If the form is being reloaded, restore the amount
	var previousAmount = $( 'input[name="amount"]' ).val();
	if ( previousAmount && previousAmount > 0  ) {
		var matched = false;
		$( 'input[name="amountRadio"]' ).each( function( index ) {
			if ( $( this ).val() == previousAmount ) {
				$( this ).attr( 'checked', true );
				matched = true;
			}
		} );
		if ( !matched ) {
			$( 'input#input_amount_other' ).attr( 'checked', true );
			$( 'input#other-amount' ).val( previousAmount );
		}
	}

} );

// set the hidden amount input to the value of the selected element
function setAmount( e ) {
	$( 'input[name="amount"]' ).val( e.val() );
}
// Display selected amount
function showAmount( e ) {
	$( "#selected-amount" ).html( + e.val() + " " + $( 'input[name="currency_code"]' ).val() );
	$( "#change-amount" ).show();
}
function validateAmount() {
	var error = true;
	var amount = $( 'input[name="amount"]' ).val(); // get the amount
	// Normalize weird amount formats.
	// Don't mess with these unless you know what you're doing.
	amount = amount.replace( /[,.](\d)$/, '\:$10' );
	amount = amount.replace( /[,.](\d)(\d)$/, '\:$1$2' );
	amount = amount.replace( /[,.]/g, '' );
	amount = amount.replace( /:/, '.' );
	$( 'input[name="amount"]' ).val( amount ); // set the new amount back into the form

	// Check amount is a real number, sets error as true (good) if no issues
	error = ( amount == null || isNaN( amount ) || amount.value <= 0 );

	// Check amount is at least the minimum
	var currency_code = $( 'input[name="currency_code"]' ).val();
	if ( typeof( wgCurrencyMinimums[currency_code] ) == 'undefined' ) {
		wgCurrencyMinimums[currency_code] = 1;
	}
	if ( amount < wgCurrencyMinimums[currency_code] || error ) {
		alert( mw.msg( 'donate_interface-smallamount-error' ).replace( '$1', wgCurrencyMinimums[currency_code] + ' ' + currency_code ) );
		error = true;
	}
	return !error;
}