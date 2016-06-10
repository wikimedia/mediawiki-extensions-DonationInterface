window.displayCreditCardForm = function () {
	$( '#payment' ).empty();
	// Load wait spinner
	$( '#payment' ).append( '<br/><br/><div style="text-align:center"><img alt="loading" src="' + mw.config.get( 'wgScriptPath' ) +
		'/extensions/DonationInterface/gateway_forms/includes/loading-white.gif" /></div>' );

	var currencyField, currency_code, stateField, state, countryField, country, sendData,
		language = 'en'; // default value is English

	language = $( 'input[name="language"]' ).val();

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
		gateway: 'globalcollect',
		currency_code: currency_code,
		amount: $( 'input[name="amount"]' ).val(),
		fname: $( 'input[name="fname"]' ).val(),
		lname: $( 'input[name="lname"]' ).val(),
		street: $( 'input[name="street"]' ).val(),
		city: $( 'input[name="city"]' ).val(),
		state: state,
		zip: $( 'input[name="zip"]' ).val(),
		email: $( 'input[name="email"]' ).val(),
		country: country,
		payment_method: 'cc',
		language: language,
		card_type: $( 'input[name="cardtype"]:checked' ).val().toLowerCase(),
		utm_source: $( 'input[name="utm_source"]' ).val(),
		utm_campaign: $( 'input[name="utm_campaign"]' ).val(),
		utm_medium: $( 'input[name="utm_medium"]' ).val(),
		referrer: $( 'input[name="referrer"]' ).val(),
		recurring: $( 'input[name="recurring"]' ).val(),
		format: 'json'
	};

	// If the field, street_supplemental, exists add it to sendData
	if ( $( 'input[name="street_supplemental"]' ).length ) {
		sendData.street_supplemental = $( 'input[name="street_supplemental"]' ).val();
	}

	mediaWiki.toggleCreditCardRadios( false );

	$.ajax( {
		url: mediaWiki.util.wikiScript( 'api' ),
		data: sendData,
		dataType: 'json',
		type: 'GET',
		success: function ( data ) {
			if ( !data || data.error !== undefined ) {
				alert( mediaWiki.msg( 'donate_interface-error-msg-general' ) );
				$( '#payment' ).empty(); // Hide spinner
				$( '#paymentContinue' ).show(); // Show continue button in 2nd section
			} else if ( data.result !== undefined ) {
				if ( data.result.errors ) {
					$( '#payment' ).empty(); // Hide spinner
					mediaWiki.donationInterface.validation.showErrors( data.result.errors );
					$( '#paymentContinue' ).show(); // Show continue button in 2nd section
				} else if ( data.result.formaction ) {
					mediaWiki.generatePaymentForm( data );
				}
			}
		},
		error: function ( xhr ) {
			$( '#payment' ).empty(); // Hide spinner
			alert( mediaWiki.msg( 'donate_interface-error-msg-general' ) );
		},
		complete: function ( xhr ) {
			// Make sure our radio buttons are reenabled at some point.
			window.setTimeout( function () {
				mediaWiki.toggleCreditCardRadios( true );
			}, 5000 );
		}
	} );
};
