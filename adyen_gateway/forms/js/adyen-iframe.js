( function ( $, mw ) {
	var di = mw.donationInterface,
		resultFunction,
		skinCodes = mw.config.get( 'wgAdyenGatewaySkinCodes' ),
		ios = /iPad|iPhone|iPod/.test( navigator.userAgent ) && !window.MSStream,
		safari = window.safari !== undefined,
		isRTBT = false;

	function redirect( result ) {
		var $pForm, $payment = $( '#payment-form' ),
			url = result.redirect;
		$pForm = $(
			'<form>', {
				method: 'post',
				action: url,
				id: 'submit-payment'
			}
		);
		populateHiddenFields( result.formData, $pForm );
		$payment.append( $pForm );

		$pForm.prop( 'action', url );
		$pForm.submit();
	}

	function showIframe( result ) {
		var $pForm, $payment = $( '#payment-form' );

		// Empty the div; add the target iframe; then submit the request for the iframe contents
		$payment.append(
			$(
				'<iframe>', {
					style: 'display: none; width: 100%;',
					height: 500,
					frameborder: 0,
					name: 'adyen-iframe',
					id: 'adyen-iframe'
				}
			)
		);

		$pForm = $(
			'<form>', {
				method: 'post',
				action: result.iframe,
				target: 'adyen-iframe',
				id: 'fetch-iframe-form'
			}
		);
		populateHiddenFields( result.formData, $pForm );
		$payment.append( $pForm );

		$payment.find( '#fetch-iframe-form' ).submit();

		// hide our continue button so that people don't get
		// confused with two of them
		$( '#paymentContinueBtn' ).hide();
		// Don't let people edit name, address, or email, since we won't
		// see any changes they make while the iframe is open.
		di.forms.disableInput();
		$( '#adyen-iframe' ).show( 'blind' );
	}

	// iframe is base
	resultFunction = showIframe;
	$( '#processor_form' ).val( skinCodes.base );
	if ( $( '#payment_method' ).val() === 'rtbt' ) {
		isRTBT = true;
	}
	if ( ios || safari || isRTBT ) {
		resultFunction = redirect;
		$( '#processor_form' ).val( skinCodes.redirect );
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
