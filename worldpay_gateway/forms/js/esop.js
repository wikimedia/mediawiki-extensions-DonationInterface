$( document ).ready( function () {
	var form = $( '#payment-form' )[0];

	$( '#paymentContinue' ).show();

	$( '#paymentContinueBtn' ).click( function () {
		if ( !$( this ).hasClass( 'enabled' ) ) {
			return false;
		}
		if ( window.validate_form( form ) ) {
			submitForm();
		}
	});

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
			email: $( '#email' ).val()
		};

		$.ajax({
			url: mw.util.wikiScript( 'api' ),
			data: postdata,
			dataType: 'json',
			type: 'POST',
			success: function ( data ) {
				if ( data.errors ) {
					// TODO: This sucks; improve it
					// Form fields have errors; each subkey in this array
					// corresponds to a form field with an error
					var errors = [];
					$.each( data.errors, function ( idx, str ) {
						errors.push( str );
					});
					window.alert( errors.join( '\n' ) );
					$( '#paymentContinueBtn' ).addClass( 'enabled' );
				} else if ( data.ottResult ) {
					$( '#payment-form' ).append(
						'<div style="display: none" id="payment-iframe">' +
						'<iframe src="' + data.ottResult.wp_redirect_url + '" height="550px" frameborder="0"></iframe>' +
						'</div>'
					);
					$( '#payment-iframe' ).show( 'blind' );
				} else {
					window.alert( mw.msg( 'donate_interface-error-msg-general' ) );
					$( '#paymentContinueBtn' ).addClass( 'enabled' );
				}
			},
			error: function ( xhr ) {
				window.alert( mw.msg( 'donate_interface-error-msg-general' ) );
				$( '#paymentContinueBtn' ).addClass( 'enabled' );
			},
			complete: function () {
				$('#overlay').hide();
			}
		});
	}
});
