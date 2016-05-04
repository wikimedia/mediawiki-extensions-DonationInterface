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
		$( '#payment-form' )[ 0 ].submit();
	}

	di.forms = {
		disable: disableForm,
		enable: enableForm,
		// Gateways with more complex form submission can overwrite this
		// property with their own submission function.
		submit: submitForm
	};

	$( function () {
		// If a submethod is already selected on page load, show the continue button
		if ( $( 'input[name="payment_submethod"]:checked' ).length > 0 ) {
			$( '#paymentContinue' ).show();
		}

		// Submit on submethod click if valid, otherwise show continue button.
		$( 'input[name="payment_submethod"]' ).on( 'click', function () {
			if ( di.validation.validateAmount() && di.validation.validatePersonal() ) {
				di.forms.submit();
			} else {
				$( '#paymentContinue' ).show();
			}
		} );

		$( '#paymentContinueBtn' ).on( 'click', function () {
			if ( di.validation.validateAmount() && di.validation.validatePersonal() ) {
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
