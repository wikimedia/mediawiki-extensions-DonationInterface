/*global amountErrors:true, billingErrors:true, paymentErrors:true, validate_personal:true, validateAmount:true, displayCreditCardForm:true*/

window.displayCreditCardForm = function () {
	$( '#payment' ).empty();
	// Load wait spinner
	$( '#payment' ).append( '<br/><br/><img alt="loading" src="' + mw.config.get( 'wgScriptPath' ) +
		'/extensions/DonationInterface/gateway_forms/includes/loading-white.gif" />' );

	var currencyField, currency_code, stateField, state, countryField, country, sendData,
		$payment, $pForm,
		language = 'en', // default value is English
		matches = document.location.href.match(/uselang=(\w+)/i); // fine the real language

	if ( matches && matches[1] ) {
		language = matches[1];
	}

	currencyField = document.getElementById( 'input_currency_code' );
	currency_code = '';
	if ( currencyField && currencyField.type === 'select-one' ) { // currency is a dropdown select
		currency_code = $( 'select#input_currency_code option:selected' ).val();
	} else {
		currency_code = $( 'input[name="currency_code"]' ).val();
	}

	stateField = document.getElementById( 'state' );
	state = '';
	if ( stateField && stateField.type === 'select-one' ) { // state is a dropdown select
		state = $( 'select#state option:selected' ).val();
	} else {
		state = $( 'input[name="state"]' ).val();
	}

	countryField = document.getElementById( 'country' );
	country = '';
	if ( countryField && countryField.type === 'select-one' ) { // country is a dropdown select
		country = $( 'select#country option:selected' ).val();
	} else {
		country = $( 'input[name="country"]' ).val();
	}

	sendData = {
		action: 'donate',
		gateway: 'adyen',
		currency_code: currency_code,
		amount: $( 'input[name="amount"]' ).val(),
		fname: $( 'input[name="fname"]' ).val(),
		lname: $( 'input[name="lname"]' ).val(),
		street: $( 'input[name="street"]' ).val(),
		city: $( 'input[name="city"]' ).val(),
		state: state,
		zip: $( 'input[name="zip"]' ).val(),
		emailAdd: $( 'input[name="emailAdd"]' ).val(),
		country: country,
		payment_method: 'cc',
		language: language,
		contribution_tracking_id: $( 'input[name="contribution_tracking_id"]' ).val(),
		utm_source: $( 'input[name="utm_source"]' ).val(),
		utm_campaign: $( 'input[name="utm_campaign"]' ).val(),
		utm_medium: $( 'input[name="utm_medium"]' ).val(),
		referrer: $( 'input[name="referrer"]' ).val(),
		recurring: $( 'input[name="recurring"]' ).val(),
		format: 'json'
	};

	// If the field, street_supplemental, exists add it to sendData
	if ( $('input[name="street_supplemental"]').length ) {
		sendData.street_supplemental = $( 'input[name="street_supplemental"]' ).val();
	}

	$.ajax( {
		url: mw.util.wikiScript( 'api' ),
		data: sendData,
		dataType: 'json',
		type: 'GET',
		success: function ( data ) {
			if ( typeof data.error !== 'undefined' ) {
				alert( mw.msg( 'donate_interface-error-msg-general' ) );
				$( '#paymentContinue' ).show(); // Show continue button in 2nd section
			} else if ( typeof data.result !== 'undefined' ) {
				if ( data.result.errors ) {
					$.each( data.result.errors, function ( index, value ) {
						alert( value ); // Show them the error
						$( '#paymentContinue' ).show(); // Show continue button in 2nd section
					} );
				} else {
					if ( data.result.formaction && data.result.gateway_params ) {
						$payment = $( '#payment' );

						// Empty the div; add the target iframe; then submit the request for the iframe contents
						$payment.empty();
						$payment.append( $( '<iframe></iframe>', {
							width: 400,
							height: 225,
							frameborder: 0,
							name: 'adyen-iframe'
						} ) );

						$pForm = $( '<form></form>', {
							method: 'post',
							action: data.result.formaction,
							target: 'adyen-iframe',
							id: 'fetch-iframe-form'
						} );
						$.each( data.result.gateway_params, function ( key, value ) {
							$pForm.append( $( '<input>', { type: 'hidden', name: key, value: value } ) );
						} );
						$payment.append( $pForm );

						$payment.find( '#fetch-iframe-form' ).submit();
					}
				}
			}
		},
		error: function ( xhr ) {
			alert( mw.msg( 'donate_interface-error-msg-general' ) );
		}
	} );
};

/*
 * The following variable are declared inline in webitects_2_3step.html:
 *   amountErrors, billingErrors, paymentErrors, scriptPath, actionURL
 */
$( document ).ready( function () {

	// check for RapidHtml errors and display, if any
	var temp, e, f, g,
		amountErrorString = '',
		billingErrorString = '',
		paymentErrorString = '';

	// generate formatted errors to display
	temp = [];
	for ( e in amountErrors ) {
		if ( amountErrors[e] !== '' ) {
			temp[temp.length] = amountErrors[e];
		}
	}
	amountErrorString = temp.join( '<br />' );

	temp = [];
	for ( f in billingErrors ) {
		if ( billingErrors[f] !== '' ) {
			temp[temp.length] = billingErrors[f];
		}
	}
	billingErrorString = temp.join( '<br />' );

	temp = [];
	for ( g in paymentErrors ) {
		if ( paymentErrors[g] !== '' ) {
			temp[temp.length] = paymentErrors[g];
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

	$( '#paymentContinueBtn' ).on( 'click', function () {
		if ( validate_personal( document.payment ) && validateAmount() ) {
			$( '#payment' ).animate( { height: '250px' }, 1000 );
			displayCreditCardForm();
			// hide the continue button so that people don't get confused with two of them
			$( '#paymentContinue' ).hide();
		}
	} );
} );
