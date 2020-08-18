( function ( $, mw ) {
	var di = mw.donationInterface,
		skinCodes = mw.config.get( 'wgAdyenGatewaySkinCodes' );

	function redirect( result ) {
		var $pForm, $payment = $( '#payment-form' ),
			url = result.redirect;
		$pForm = $(
			'<form></form>', {
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

	$( '#processor_form' ).val( skinCodes.redirect );

	di.forms.submit = function () {
		di.forms.callDonateApi( redirect );
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
