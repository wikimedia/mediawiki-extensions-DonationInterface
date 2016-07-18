( function ( $, mw ) {
	var di = mw.donationInterface;

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
		$.each(
			result.gateway_params, function ( key, value ) {
				$pForm.append(
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
		$payment.append( $pForm );

		$payment.find( '#fetch-iframe-form' ).submit();

		// hide our continue button so that people don't get
		// confused with two of them
		$( '#paymentContinueBtn' ).hide();
		$( '#adyen-iframe' ).show( 'blind' );
	}

	di.forms.submit = function () {
		di.forms.callDonateApi( showIframe );
	};
} )( jQuery, mediaWiki );
