$( document ).ready( function () {
	var form = $( '#payment-form' )[ 0 ];

	if ( $( 'input[name="payment_submethod"]:checked' ).length > 0 ) {
		$( '#paymentContinue' ).show();
	}

	$( '#paymentContinueBtn' ).click( function () {
		if ( !$( this ).hasClass( 'enabled' ) ) {
			return false;
		}
		if ( window.validate_form( form ) ) {
			submitForm();
		}
	} );

	// Submit on submethod selection if valid, otherwise show continute button.
	$( 'input[name="payment_submethod"]' ).on( 'change', function () {
		if ( window.validate_form( form ) ) {
			submitForm();
		} else {
			$( '#paymentContinue' ).show();
		}
	} );

	function submitForm() {
		$( '#overlay' ).show();
		$( '#paymentContinueBtn' ).removeClass( 'enabled' );

		var postdata = {
			action: 'di_wp_validate',
			format: 'json',
			ffname: 'wp-if',
			amount: $( '#amount' ).val(),
			fname: $( '#fname' ).val(),
			lname: $( '#lname' ).val(),
			email: $( '#email' ).val(),
			token: $( 'input[name=token]' ).val(),
			payment_submethod: $( 'input[name=payment_submethod]:checked' ).val()
		};

		$.ajax( {
			url: mediaWiki.util.wikiScript( 'api' ),
			data: postdata,
			dataType: 'json',
			type: 'POST',
			success: function ( data ) {
				if ( data.errors ) {
					// TODO: This sucks; improve it
					// Form fields have errors; each subkey in this array
					// corresponds to a form field with an error
					var errors = [],
					// Optionally refresh after displaying errors.
						refresh = false;
					$.each( data.errors, function ( idx, str ) {
						if ( idx === 'token-mismatch' ) {
							refresh = true;
						}
						errors.push( str );
					} );
					window.alert( errors.join( '\n' ) );
					if ( refresh ) {
						window.location.reload();
					}
					$( '#paymentContinue' ).show();
					$( '#paymentContinueBtn' ).addClass( 'enabled' );
				} else if ( data.ottResult ) {
					$( '#payment-form' ).append(
						'<div style="display: none" id="payment-iframe">' +
						'<iframe src="' + data.ottResult.wp_redirect_url + '" height="550px" frameborder="0"></iframe>' +
						'</div>'
					);
					$( '#payment-iframe' ).show( 'blind' );
					setTimeout( function () {
						window.alert( mediaWiki.msg( 'donate_interface-cc-token-expired' ) );
						window.location.reload( true );
					}, mediaWiki.config.get( 'wgWorldpayGatewayTokenTimeout' ) );
				} else {
					window.alert( mediaWiki.msg( 'donate_interface-error-msg-general' ) );
					$( '#paymentContinue' ).show();
					$( '#paymentContinueBtn' ).addClass( 'enabled' );
				}
			},
			error: function ( xhr ) {
				window.alert( mediaWiki.msg( 'donate_interface-error-msg-general' ) );
				$( '#paymentContinue' ).show();
				$( '#paymentContinueBtn' ).addClass( 'enabled' );
			},
			complete: function () {
				$( '#overlay' ).hide();
			}
		} );
	}
} );
