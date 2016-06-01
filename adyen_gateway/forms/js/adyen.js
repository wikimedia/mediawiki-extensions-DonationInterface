( function ( $, mw ) {
	var di = mw.donationInterface;

	di.forms.submit = function () {
		di.forms.disable();
		$( '#paymentContinueBtn' ).removeClass( 'enabled' );

		var sendData,
			$payment,
			$pForm,
			currency_code = 'USD';

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
			language: $( '#language' ).val(),
			payment_submethod: $( 'input[name="payment_submethod"]:checked' ).val().toLowerCase(),
			contribution_tracking_id: $( '#contribution_tracking_id' ).val(),
			utm_source: $( '#utm_source' ).val(),
			utm_campaign: $( '#utm_campaign' ).val(),
			utm_medium: $( '#utm_medium' ).val(),
			referrer: $( '#referrer' ).val(),
			recurring: $( '#recurring' ).val(),
			wmf_token: $( '#wmf_token' ).val(),
			format: 'json'
		};

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
						mw.donationInterface.validation.showErrors( data.result.errors );
						$( '#paymentContinue' ).show(); // Show continue button in 2nd section
					} else if ( data.result.formaction && data.result.gateway_params ) {
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
							$pForm.append( $( '<input>', {
								type: 'hidden',
								name: key,
								value: value
							} ) );
						} );
						$payment.append( $pForm );

						$payment.find( '#fetch-iframe-form' ).submit();

						// hide our continue button so that people don't get
						// confused with two of them
						$( '#paymentContinueBtn' ).hide();
						$( '#adyen-iframe' ).show( 'blind' );
					}
				}
			},
			error: function ( xhr ) {
				alert( mw.msg( 'donate_interface-error-msg-general' ) );
			},
			complete: function () {
				di.forms.enable();
			}
		} );
	};
} )( jQuery, mediaWiki );
