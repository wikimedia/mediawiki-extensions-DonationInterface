/**
 * Core functionality for DonationInterface forms
 */
( function ( $, mw ) {
	var di = mw.donationInterface; // Defined in ext.donationInterface.validation.js

	/**
	 * Disable all interaction with the form, including buttons.
	 * Usually done by drawing a semi-opaque overlay.
	 */
	function disableForm() {
		$( '#overlay' ).show();
	}

	/**
	 * Mark all form input fields disabled. This can be used to indicate that
	 * no revision to donor info is possible after a card iframe is opened.
	 */
	function disableInput() {
		$( '[type=text], [type=number], [type=email], select' ).each( function () {
			$( this ).attr( 'disabled', true );
		} );
	}

	/**
	 * Makes a form disabled with disableForm usable again.
	 */
	function enableForm() {
		$( '#overlay' ).hide();
	}

	/**
	 * Re-enable input fields disabled with disableInput.
	 */
	function enableInput() {
		$( '[type=text], [type=number], [type=email] select' ).each( function () {
			$( this ).removeAttr( 'disabled' );
		} );
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
	 * Get a trinary value from a checkbox that may exist, falling back
	 *  to a querystring value.
	 *  '0' = checkbox shown and not checked, or 0 on querystring
	 *  '1' = checkbox shown and checked, or 1 on querystring
	 *  '' = checkbox not shown, querystring value missing
	 *
	 * @return {string}
	 */
	function getOptIn() {
		var val, element = $( 'input[name=opt_in]:checked' );
		if ( element.length === 1 ) {
			val = element.val();
		} else {
			val = mw.util.getParamValue( 'opt_in' );
			if ( val === null ) {
				val = '';
			}
		}
		return val;
	}

	/**
	 * Call the generic donation API and handle errors or execute a callback
	 *
	 * @param {function(result)} successCallback will be called with response's 'result' property
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
			contact_id: $( '#contact_id' ).val(),
			contact_hash: $( '#contact_hash' ).val(),
			currency: $( '#currency' ).val(),
			amount: $( '#amount' ).val(),
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
			processor_form: $( '#processor_form' ).val(),
			issuer_id: $( '#issuer_id' ).val(),
			utm_source: $( '#utm_source' ).val(),
			utm_campaign: $( '#utm_campaign' ).val(),
			utm_medium: $( '#utm_medium' ).val(),
			referrer: $( '#referrer' ).val(),
			recurring: $( '#recurring' ).val(),
			variant: $( '#variant' ).val(),
			wmf_token: $( '#wmf_token' ).val(),
			opt_in: getOptIn(),
			employer: $( '#employer' ).val(),
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
		disableInput: disableInput,
		enable: enableForm,
		enableInput: enableInput,
		clean: cleanInput,
		// Gateways with more complex form submission can overwrite this
		// property with their own submission function.
		submit: submitForm,
		callDonateApi: callDonateApi,
		isIframe: isIframe,
		getOptIn: getOptIn
	};

	$( function () {

		$( '#first_name' ).focus();

		// If submethods are visible, and a submethod is already selected on
		// page load, clear it.
		if ( $( 'input[name="payment_submethod"]:checked:visible' ).length > 0 ) {
			$( 'input[name="payment_submethod"]' ).attr( 'checked', false );
		}

		// Submit on submethod click if valid, otherwise do nothing.
		$( 'input[name="payment_submethod"]' ).on( 'click', function () {
			if ( di.validation.validate() ) {
				di.forms.submit();
			} else {
				return false;
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
				di.forms.enableInput();
			}
		);
	} );
} )( jQuery, mediaWiki );
