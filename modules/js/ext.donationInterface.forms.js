/**
 * Core functionality for DonationInterface forms
 */
( function ( $, mw ) {
	var di = mw.donationInterface; // Defined in ext.donationInterface.validation.js

	function disableForm() {
		$( '#overlay' ).show();
	}

	function enableForm() {
		$( '#overlay' ).hide();
	}

	/**
	 * Submit a basic form.
	 */
	function submitForm() {
		di.forms.disable();
		di.forms.clean();
		$( '#payment-form' )[ 0 ].submit();
	}

	function cleanInput() {
		// Trim all the trimmable inputs
		$( '[type=text], [type=number], [type=email]' ).each( function () {
			$( this ).val( $.trim( this.value ) );
		} );
	}

	/**
	 * Call the generic donation API and handle errors or execute a callback
	 *
	 * @param {function()} successCallback will be called with response's 'result' property
	 */
	function callDonateApi( successCallback ) {
		di.forms.disable();
		di.forms.clean();
		$( '#topError' ).html( '' );
		$( '#errorReference' )
			.removeClass( 'errorMsg' )
			.addClass( 'errorMsgHide' );
		$( '#paymentContinueBtn' ).removeClass( 'enabled' );

		var sendData = {
			action: 'donate',
			gateway: $( '#gateway' ).val(),
			currency: $( '#currency' ).val(),
			gross: $( '#gross' ).val(),
			first_name: $( '#first_name' ).val(),
			last_name: $( '#last_name' ).val(),
			street_address: $( '#street_address' ).val(),
			city: $( '#city' ).val(),
			state_province: $( '#state_province' ).val(),
			postal_code: $( '#postal_code' ).val(),
			email: $( '#email' ).val(),
			country: $( '#country' ).val(),
			payment_method: $( '#payment_method' ).val(),
			language: $( '#language' ).val(),
			payment_submethod: $( 'input[name="payment_submethod"]:checked' ).val().toLowerCase(),
			issuer_id: $( '#issuer_id' ).val(),
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
					// FIXME alert sux
					alert( mw.msg( 'donate_interface-error-msg-general' ) );
					$( '#paymentContinue' ).show(); // Show continue button in 2nd section
				} else if ( typeof data.result !== 'undefined' ) {
					if ( data.result.errors ) {
						mw.donationInterface.validation.showErrors( data.result.errors );
						$( '#paymentContinue' ).show(); // Show continue button in 2nd section
					} else {
						successCallback( data.result );
					}
				}
			},
			error: function ( xhr ) {
				// FIXME too
				alert( mw.msg( 'donate_interface-error-msg-general' ) );
			},
			complete: function () {
				di.forms.enable();
			}
		} );
	}

	function isIframe() {
		var payment_method = $( '#payment_method' ).val();

		switch ( payment_method ) {
		case 'cc':
			return true;
		default:
			return false;
		}
	}

	// FIXME: move function declarations into object
	di.forms = {
		disable: disableForm,
		enable: enableForm,
		clean: cleanInput,
		// Gateways with more complex form submission can overwrite this
		// property with their own submission function.
		submit: submitForm,
		callDonateApi: callDonateApi,
		isIframe: isIframe
	};

	$( function () {

		$( '#first_name' ).focus();

		// If a submethod is already selected on page load, show the continue button
		if ( $( 'input[name="payment_submethod"]:checked' ).length > 0 ) {
			$( '#paymentContinue' ).show();
		}

		// Submit on submethod click if valid, otherwise show continue button.
		$( 'input[name="payment_submethod"]' ).on( 'click', function () {
			if ( di.validation.validate() ) {
				di.forms.submit();
			} else {
				$( '#paymentContinue' ).show();
			}
		} );

		$( '#paymentContinueBtn' ).on( 'click', function () {
			if ( di.validation.validate() ) {
				di.forms.submit();
			}
		} );

		// Magic to hopefully disable the spinner in case we are returned to this
		// page via the Back button.
		$( window ).on(
			'unload', function () {
				// wrapped in case it is overwritten
				di.forms.enable();
			}
		);
	} );
} )( jQuery, mediaWiki );
