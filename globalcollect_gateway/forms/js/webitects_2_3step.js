/*
 * The following variable are declared inline in webitects_2_3step.html:
 *   amountErrors, billingErrors, paymentErrors, scriptPath, actionURL
 */
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
		prevError = true;
		showStep2(); // init the headers
		showStep3();
		showStep1(); // should be default, but ensure
	}
	if ( billingErrorString != "" ) {
		$( "#billingErrorMessages" ).html( billingErrorString );
		if ( !prevError ) {
			showStep1(); // init the headers
			showStep3();
			showStep2();
			prevError = true;
		}
		showAmount( $( 'input[name="amount"]' ) ); // lets go ahead and assume there is something to show
	}
	if ( paymentErrorString != "" ) {
		$( "#paymentErrorMessages" ).html( paymentErrorString );
		if ( !prevError ) {
			showStep1(); // init the headers
			showStep2();
			showStep3();
		}
		showAmount( $( 'input[name="amount"]' ) ); // lets go ahead and assume there is something to show
	}

	$( "#cc" ).click( function() {
		/* safety check for people who hit the back button */
		checkedValue = $( "input[name='amountRadio']:checked" ).val();
		if ( $( 'input[name="amount"]' ).val() == '0.00' && checkedValue && !isNaN( checkedValue ) ) {
			setAmount( checkedValue );
		}
		if ( validateAmount() ) {
			showAmount( $( 'input[name="amount"]' ) );
			showStep2();
		}
	} );

	$( "#pp" ).click( function() {
		/* safety check for people who hit the back button */
		checkedValue = $( "input[name='amountRadio']:checked" ).val();
		if ( $( 'input[name="amount"]' ).val() == '0.00' && checkedValue && !isNaN( checkedValue ) ) {
			setAmount( checkedValue );
		}
		if ( validateAmount() ) {
			// set the action to go to PayPal
			$( 'input[name="gateway"]' ).val( "paypal" );
			$( 'input[name="PaypalRedirect"]' ).val( "1" );
			$( "#loading" ).html( '<img alt="loading" src="'+mw.config.get( 'wgScriptPath' )+'/extensions/DonationInterface/gateway_forms/includes/loading-white.gif" /> Redirecting to PayPal…' );
			document.paypalcontribution.action = actionURL;
			document.paypalcontribution.submit();
		}
	} );
	$( "#paymentContinueBtn" ).live( "click", function() {
		if ( validate_personal( document.paypalcontribution ) ) {
			displayCreditCardForm()
		}
	} );
	// Set the cards to progress to step 3
	$( ".cardradio" ).live( "click", function() {
		if ( validate_personal( document.paypalcontribution ) ) {
			displayCreditCardForm()
		}
		else {
			// show the continue button to indicate how to get to step 3 since they
			// have already clicked on a card image
			$( "#paymentContinue" ).show();
		}
	} );

	$( "#submitcreditcard" ).click( function() {
		// set country to US TODO: make this dynamic
		$( 'input[name="country"]' ).val( "US" );

		if ( validate_cc() ) {
			// set the hidden expiration date input from the two selects
			$( 'input[name="expiration"]' ).val(
				$( 'select[name="mos"]' ).val() + $( 'select[name="year"]' ).val().substring( 2, 4 )
			);
			document.paypalcontribution.action = actionURL;
			document.paypalcontribution.submit();
		}
	} );
	// init all of the header actions
	$( "#step1header" ).click( function() {
		showStep1();
	} );
	$( "#step2header" ).click( function() {
		showStep2();
	} );
	$( "#step3header" ).click( function() {
		displayCreditCardForm();
	} );
	// Set selected amount to amount
	$( 'input[name="amountRadio"]' ).click( function() {
		setAmount( $( this ) );
	} );
	// reset the amount field when "other" is changed
	$( "#other-amount" ).keyup( function() {
		setAmount( $( this ) );
	} );

	// show the CVV help image on click
	$( "#where" ).click( function() {
		$( "#codes" ).toggle();
		return false;
	} );

} );

function displayCreditCardForm() {
	$( '#payment' ).empty();
	// Load wait spinner
	$( '#payment' ).append( '<br/><br/><br/><img alt="loading" src="'+mw.config.get( 'wgScriptPath' )+'/extensions/DonationInterface/gateway_forms/includes/loading-white.gif" />' );
	showStep3(); // Open the 3rd section
	var language = 'en'; // default value is English
	var matches = document.location.href.match(/uselang=(\w+)/i); // fine the real language
	if ( matches && matches[1] ) {
		language = matches[1];
	}
	var sendData = {
		'action': 'donate',
		'gateway': 'globalcollect',
		'currency': 'USD',
		'amount': $( "input[name='amount']" ).val(),
		'fname': $( "input[name='fname']" ).val(),
		'lname': $( "input[name='lname']" ).val(),
		'street': $( "input[name='street']" ).val(),
		'city': $( "input[name='city']" ).val(),
		'state': $( "input[name='state']" ).val(),
		'zip': $( "input[name='zip']" ).val(),
		'emailAdd': $( "input[name='emailAdd']" ).val(),
		'country': $( "input[name='country']" ).val(),
		'payment_method': 'cc',
		'language': language,
		'card_type': $( "input[name='cardtype']:checked" ).val().toLowerCase(),
		'contribution_tracking_id': $( "input[name='contribution_tracking_id']" ).val(),
		'numAttempt': $( "input[name='numAttempt']" ).val(),
		'utm_source': $( "input[name='utm_source']" ).val(),
		'utm_campaign': $( "input[name='utm_campaign']" ).val(),
		'utm_medium': $( "input[name='utm_medium']" ).val(),
		'format': 'json'
	};
	$.ajax( {
		'url': mw.config.get( 'wgServer' ) + mw.config.get( 'wgScriptPath' ) + '/api.php?',
		'data': sendData,
		'dataType': 'json',
		'type': 'GET',
		'success': function( data ) {
			if ( typeof data.result !== 'undefined' ) {
				if ( data.result.errors ) {
					var errors = new Array();
					$.each( data.result.errors, function( index, value ) {
						alert( value ); // Show them the error
						$( "#paymentContinue" ).show(); // Show continue button in 2nd section
						showStep2(); // Switch back to 2nd section of form
					} );
				} else {
					if ( data.result.formaction ) {
						$( '#payment' ).empty();
						// Insert the iframe into the form
						$( '#payment' ).append( 
							'<iframe src="'+data.result.formaction+'" width="318" height="314" frameborder="0"></iframe>'
						);
						
					}
				}
			}
		}
	} );
}

function showStep1() {
	// show the correct sections
	$( "#step1wrapper" ).slideDown();
	$( "#step2wrapper" ).slideUp();
	$( "#step3wrapper" ).slideUp();
	$( "#change-amount" ).hide();
	$( "#change-billing" ).show();
	$( "#change-payment" ).show();
	$( "#step1header" ).show(); // just in case
}

function showStep2() {
	if ( $( '#step3wrapper' ).is(":visible") ) {
		$( "#paymentContinue" ).show(); // Show continue button in 2nd section
	}
	// show the correct sections
	$( "#step1wrapper" ).slideUp();
	$( "#step2wrapper" ).slideDown();
	$( "#step3wrapper" ).slideUp();
	$( "#change-amount" ).show();
	$( "#change-billing" ).hide();
	$( "#change-payment" ).show();
	$( "#step2header" ).show(); // just in case
}

function showStep3() {
	// show the correct sections
	$( "#step1wrapper" ).slideUp();
	$( "#step2wrapper" ).slideUp();
	$( "#step3wrapper" ).slideDown();
	$( "#change-amount" ).show();
	$( "#change-billing" ).show();
	$( "#change-payment" ).hide();
	$( "#step3header" ).show(); // just in case
}
// Fix behavior of images in labels
// TODO: check that disabling this is okay in things other than Chrome
// $("label img").live("click", function() { $("#" + $(this).parents( "label" ).attr( "for" )).click(); });

// set the hidden amount input to the value of the selected element
function setAmount( e ) {
	$( 'input[name="amount"]' ).val( e.val() );
}
// Display selected amount
function showAmount( e ) {
	$( "#selected-amount" ).html( "($" + e.val() + ")" );
	$( "#change-amount" ).show();
}
function validateAmount() {

	// TODO: THIS NEEDS TO BE REPLACED WITH KALDARI'S CURRENCIES
	var minimums = {
		'USD' : 1,
		'CAD' : 1
	};
	var error = true;
	var amount = $( 'input[name="amount"]' ).val(); // get the amount
	amount = amount.replace( /[,.](\d)$/, '\:$10' );
	amount = amount.replace( /[,.](\d)(\d)$/, '\:$1$2' );
	amount = amount.replace( /[,.]/g, '' );
	amount = amount.replace( /:/, '.' );
	$( 'input[name="amount"]' ).val( amount ); // set the new amount back into the form

	// Check amount is a real number, sets error as true (good) if no issues
	error = ( amount == null || isNaN( amount ) || amount.value <= 0 );

	// Check amount is at least the minimum
	var currency_code = $( 'input[name="currency_code"]' ).val();
	if ( typeof( minimums[currency_code] ) == 'undefined' ) {
		minimums[currency_code] = 1;
	}
	if ( amount < minimums[currency_code] || error ) {
		alert( 'You must contribute at least $1'.replace( '$1', minimums[currency_code] + ' ' + currency_code ) );
		error = true;
	}
	return !error;
}

function validate_cc() {
	// reset the errors
	$( "#paymentErrorMessages" ).html( '' );
	var error = false;
	if ( $( 'input[name="card_num"]' ).val() == '' ) {
		$( "#paymentErrorMessages" ).append( "Please enter a valid credit card number" );
		error = true;
	}
	if ( $( 'select[name="mos"]' ).val() == '' ) {
		if ( $( "#paymentErrorMessages" ).html() != "" )
			$( "#paymentErrorMessages" ).append( "<br />" );
		$( "#paymentErrorMessages" ).append( "Please enter a valid month for the expiration date" );
		error = true;
	}
	if ( $( 'select[name="year"]' ).val() == '' ) {
		if ( $( "#paymentErrorMessages" ).html() != "" )
			$( "#paymentErrorMessages" ).append( "<br />" );
		$( "#paymentErrorMessages" ).append( "Please enter a valid year for the expiration date" );
		error = true;
	}
	if ( $( 'input[name="cvv"]' ).val() == '' ) {
		if ( $( "#paymentErrorMessages" ).html() != "" )
			$( "#paymentErrorMessages" ).append( "<br />" );
		$( "#paymentErrorMessages" ).append( "Please enter a valid security code" );
		error = true;
	}
	return !error;
}
