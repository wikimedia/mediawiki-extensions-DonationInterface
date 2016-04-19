window.displayCreditCardForm = function () {
	$( '#overlay' ).show();
	$( '#paymentContinueBtn' ).removeClass( 'enabled' );

	var sendData,
		$payment,
		$pForm,
		currency_code = 'USD',
		language = 'en', // default value is English
		matches = document.location.href.match( /uselang=(\w+)/i ); // fine the real language

	if ( matches && matches[ 1 ] ) {
		language = matches[ 1 ];
	}

	sendData = {
		action: 'donate',
		gateway: 'adyen',
		currency_code: currency_code,
		amount: $( '#amount' ).val(),
		fname: $( '#fname' ).val(),
		lname: $( '#lname' ).val(),
		street: $( '#street' ).val(),
		city: $( '#city' ).val(),
		state: $( '#state' ).val(),
		zip: $( '#zip' ).val(),
		email: $( '#email' ).val(),
		country: $( '#country' ).val(),
		payment_method: 'cc',
		language: language,
		payment_submethod: $( 'input[name="payment_submethod"]:checked' ).val().toLowerCase(),
		contribution_tracking_id: $( '#contribution_tracking_id' ).val(),
		utm_source: $( '#utm_source' ).val(),
		utm_campaign: $( '#utm_campaign' ).val(),
		utm_medium: $( '#utm_medium' ).val(),
		referrer: $( '#referrer' ).val(),
		recurring: $( '#recurring' ).val(),
		token: $( '#token' ).val(),
		format: 'json'
	};

	$.ajax( {
		url: mediaWiki.util.wikiScript( 'api' ),
		data: sendData,
		dataType: 'json',
		type: 'GET',
		success: function ( data ) {
			if ( typeof data.error !== 'undefined' ) {
				alert( mediaWiki.msg( 'donate_interface-error-msg-general' ) );
				$( '#paymentContinue' ).show(); // Show continue button in 2nd section
			} else if ( typeof data.result !== 'undefined' ) {
				if ( data.result.errors ) {
					$.each( data.result.errors, function ( index, value ) {
						alert( value ); // Show them the error
						$( '#paymentContinue' ).show(); // Show continue button in 2nd section
					} );
				} else {
					if ( data.result.formaction && data.result.gateway_params ) {
						$payment = $( '#payment-form' );

						// Empty the div; add the target iframe; then submit the request for the iframe contents
						$payment.append( $( '<iframe></iframe>', {
							style: 'display: none; width: 100%;',
							height: 500,
							frameborder: 0,
							name: 'adyen-iframe',
							id: 'adyen-iframe'
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

						$( '#adyen-iframe' ).show( 'blind' );
					}
				}
			}
		},
		error: function ( xhr ) {
			alert( mediaWiki.msg( 'donate_interface-error-msg-general' ) );
		},
		complete: function () {
			$( '#overlay' ).hide();
		}
	} );
};

$( document ).ready( function () {
	if ( $( 'input[name="payment_submethod"]:checked' ).length > 0 ) {
		$( '#paymentContinue' ).show();
	}

	$( 'input[name="payment_submethod"]' ).on( 'change', function () {
		if ( window.validateAmount() && window.validate_form( document.payment ) ) {
			window.displayCreditCardForm();
		} else {
			$( '#paymentContinue' ).show();
		}
	} );

	$( '#paymentContinueBtn' ).on( 'click', function () {
		if ( window.validateAmount() && window.validate_form( document.payment ) ) {
			window.displayCreditCardForm();
			// hide the continue button so that people don't get confused with two of them
			$( '#paymentContinue' ).hide();
		}
	} );

} );
