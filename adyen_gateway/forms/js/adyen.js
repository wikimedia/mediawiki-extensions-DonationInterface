( function ( $, mw ) {
	var di = mw.donationInterface,
		resultFunction,
        skinNames = mw.config.get( 'wgAdyenGatewaySkinNames' );

	function redirect( result ) {
		var $pForm, $payment = $( '#payment-form' );
		$pForm = $(
			'<form></form>', {
				method: 'post',
				action: result.formaction,
				id: 'submit-payment'
			}
		);
		populateHiddenFields( result.gateway_params, $pForm );
		$payment.append( $pForm );

		$pForm.prop( 'action', result.formaction );
		$pForm.submit();
	}

	function showIframe( result ) {
		var $pForm, $payment = $( '#payment-form' );

		// Empty the div; add the target iframe; then submit the request for the iframe contents
		$payment.append(
			$(
				'<iframe></iframe>', {
					style: 'display: none; width: 100%;',
					height: 500,
					frameborder: 0,
					name: 'adyen-iframe',
					id: 'adyen-iframe'
				}
			)
		);

		$pForm = $(
			'<form></form>', {
				method: 'post',
				action: result.formaction,
				target: 'adyen-iframe',
				id: 'fetch-iframe-form'
			}
		);
		populateHiddenFields( result.gateway_params, $pForm );
		$payment.append( $pForm );

		$payment.find( '#fetch-iframe-form' ).submit();

		// hide our continue button so that people don't get
		// confused with two of them
		$( '#paymentContinueBtn' ).hide();
		$( '#adyen-iframe' ).show( 'blind' );
	}

	// iframe is base
	resultFunction = showIframe;
	$( '#processor_form' ).val( skinNames.base );
	if (  window.safari !== undefined ) {
		resultFunction = redirect;
		$( '#processor_form' ).val( skinNames.redirect );
	}

	di.forms.submit = function () {
		di.forms.callDonateApi( resultFunction );
	};

	function populateHiddenFields( values, $form ) {
		$.each(
			values, function ( key, value ) {
				$form.append(
					$(
						'<input>', {
							type: 'hidden',
							name: key,
							value: value
						}
					)
				);
			}
		);
	}
} )( jQuery, mediaWiki );
