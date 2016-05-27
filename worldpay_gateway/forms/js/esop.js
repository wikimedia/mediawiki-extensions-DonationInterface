( function ( $, mw ) {
	var di = mw.donationInterface;

	di.forms.submit = function () {
		if ( !( $( '#paymentContinueBtn' ).hasClass( 'enabled' ) ) ) {
			return;
		}
		di.forms.disable();
		di.forms.clean();
		$( '#paymentContinueBtn' ).removeClass( 'enabled' );

		var postdata = {
			action: 'di_wp_validate',
			format: 'json',
			ffname: 'wp-if',
			amount: $( '#amount' ).val(),
			fname: $( '#fname' ).val(),
			lname: $( '#lname' ).val(),
			email: $( '#email' ).val(),
			wmf_token: $( 'input[name=wmf_token]' ).val(),
			payment_submethod: $( 'input[name=payment_submethod]:checked' ).val()
		};

		$.ajax( {
			url: mw.util.wikiScript( 'api' ),
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
						window.alert( mw.msg( 'donate_interface-cc-token-expired' ) );
						window.location.reload( true );
					}, mw.config.get( 'wgWorldpayGatewayTokenTimeout' ) );
				} else {
					window.alert( mw.msg( 'donate_interface-error-msg-general' ) );
					$( '#paymentContinue' ).show();
					$( '#paymentContinueBtn' ).addClass( 'enabled' );
				}
			},
			error: function ( xhr ) {
				window.alert( mw.msg( 'donate_interface-error-msg-general' ) );
				$( '#paymentContinue' ).show();
				$( '#paymentContinueBtn' ).addClass( 'enabled' );
			},
			complete: function () {
				di.forms.enable();
			}
		} );
	};
} )( jQuery, mediaWiki );
