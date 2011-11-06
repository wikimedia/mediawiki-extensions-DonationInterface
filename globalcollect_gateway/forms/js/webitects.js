
window.displayCreditCardForm = function() {
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
		'currency': $( "input[name='currency_code']" ).val(),
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


// set the hidden amount input to the value of the selected element
window.setAmount = function( e ) {
	$( 'input[name="amount"]' ).val( e.val() );
}
// Display selected amount
window.showAmount = function( e ) {
	$( "#selected-amount" ).html( "($" + e.val() + ")" );
	$( "#change-amount" ).show();
}
window.validateAmount = function() {

	// If you change these, also change in currencyRates.inc.
	var minimums = {
		'AED': 4,
		'ARS': 4,
		'AUD': 1,
		'BBD': 2,
		'BDT': 76,
		'BGN': 1.4,
		'BHD': 0.4,
		'BMD': 1,
		'BND': 1.3,
		'BOB': 7,
		'BRL': 1.7,
		'BSD': 1,
		'BZD': 2,
		'CAD': 1,
		'CHF': 0.9,
		'CLP': 494,
		'CNY': 6,
		'COP': 1910,
		'CRC': 512,
		'CZK': 18,
		'DKK': 5,
		'DOP': 38,
		'DZD': 73,
		'EEK': 11,
		'EGP': 6,
		'EUR': 0.7,
		'GBP': 0.6,
		'GTQ': 7.8,
		'HKD': 7.7,
		'HNL': 19,
		'HRK': 5,
		'HUF': 219,
		'IDR': 8960,
		'ILS': 3.6,
		'INR': 49,
		'JMD': 85,
		'JOD': 0.7,
		'JPY': 78,
		'KES': 97,
		'KRW': 1127,
		'KYD': 0.8,
		'KZT': 147,
		'LBP': 1500,
		'LKR': 110,
		'LTL': 2.5,
		'LVL': 0.5,
		'MAD': 8.1,
		'MKD': 45,
		'MUR': 29,
		'MVR': 15,
		'MXN': 13,
		'MYR': 3,
		'NOK': 5.5,
		'NZD': 1.2,
		'OMR': 0.3,
		'PAB': 1,
		'PEN': 2.7,
		'PHP': 43,
		'PKR': 86,
		'PLN': 3,
		'PYG': 4190,
		'QAR': 3.6,
		'RON': 3.1,
		'RUB': 30,
		'SAR': 3.7,
		'SEK': 6.5,
		'SGD': 1.2,
		'SVC': 8.7,
		'THB': 30,
		'TJS': 4.7,
		'TND': 1.4,
		'TRY': 1.7,
		'TTD': 6,
		'TWD': 30,
		'UAH': 8,
		'USD': 1,
		'UYU': 19,
		'UZS': 1760,
		'VND': 21000,
		'XAF': 470,
		'XCD': 2.7,
		'XOF': 476,
		'ZAR': 7.8
	};
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
	if ( typeof( minimums[currency_code] ) == 'undefined' ) {
		minimums[currency_code] = 1;
	}
	if ( amount < minimums[currency_code] || error ) {
		alert( 'You must contribute at least $1'.replace( '$1', minimums[currency_code] + ' ' + currency_code ) );
		error = true;
	}
	return !error;
}

window.validate_cc = function() {
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