/**
 * Core functionality for DonationInterface forms
 *
 * @param $
 * @param mw
 */
( function ( $, mw ) {
	const di = mw.donationInterface; // Defined in ext.donationInterface.validation.js

	// Common helper functions
	function disablePaymentSubmitButton() {
		$( '#paymentSubmitBtn' ).prop( 'disabled', true );
		$( '#paymentSubmitBtn' ).removeClass( 'enabled' ).addClass( 'disabled' );
	}

	function enablePaymentSubmitButton() {
		$( '#paymentSubmitBtn' ).prop( 'disabled', false );
		$( '#paymentSubmitBtn' ).removeClass( 'disabled' ).addClass( 'enabled' );
	}

	/**
	 * Disable all interaction with the form, including buttons.
	 * Usually done by drawing a semi-opaque overlay.
	 */
	function disableForm() {
		$( '#overlay' ).show();
		disablePaymentSubmitButton();
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
		enablePaymentSubmitButton();
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

	function resetSubmethod() {
		const $submethodInput = $( 'input:radio[name=payment_submethod]' );
		if ( $submethodInput.length > 1 ) {
			$submethodInput.prop( 'checked', false );
		}
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
		let val, $element = $( 'input[name=opt_in]:checked' );
		if ( $element.length === 1 ) {
			val = $element.val();
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
	 * @param {Array} extraData will be merged into the data collected from the form
	 * @param {string} action action param to pass to MW API, defaults to 'donate'
	 */
	function callDonateApi( successCallback, extraData, action ) {
		di.forms.disable();
		di.forms.clean();
		$( '#topError' ).html( '' );
		$( '#errorReference' )
			.removeClass( 'errorMsg' )
			.addClass( 'errorMsgHide' );
		$( '#paymentContinueBtn' ).removeClass( 'enabled' );

		let sendData,
			paymentSubmethod;

		if ( typeof $( 'input[name="payment_submethod"]:checked' ).val() === 'undefined' ) {
			paymentSubmethod = '';
		} else {
			paymentSubmethod = $( 'input[name="payment_submethod"]:checked' ).val().toLowerCase();
		}

		sendData = {
			action: action || 'donate',
			bin_hash: $( '#bin_hash' ).val(),
			gateway: $( '#gateway' ).val(),
			checksum: $( '#checksum' ).val(),
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
			phone: $( '#phone' ).val(),
			email: $( '#email' ).val(),
			country: $( '#country' ).val(),
			payment_method: $( '#payment_method' ).val(),
			language: $( '#language' ).val(),
			payment_submethod: paymentSubmethod,
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
			employer_id: $( '#employer_id' ).val(),
			street_number: $( '#street_number' ).val(),
			format: 'json'
		};

		if ( extraData ) {
			Object.assign( sendData, extraData );
		}

		// If debug logging is enabled and there are debug messages, send them.
		if ( mw.config.get( 'wgDonationInterfaceLogDebug' ) &&
			di.forms.debugMessages.length > 0 ) {
			sendData.debug_messages = di.forms.debugMessages.join( '\n' );
		}

		$.ajax( {
			url: mw.util.wikiScript( 'api' ),
			data: sendData,
			dataType: 'json',
			type: 'POST',
			success: function ( data ) {
				if ( typeof data.error !== 'undefined' ) {
					// FIXME alert sux
					alert( mw.msg( 'donate_interface-error-msg-general' ) );
					// Show continue button in 2nd section if it exists
					$( '#paymentContinue' ).show();
				} else if ( typeof data.result !== 'undefined' ) {
					if ( data.result.errors ) {
						mw.donationInterface.validation.showErrors( data.result.errors );
						// Show continue button in 2nd section if it exists
						$( '#paymentContinue' ).show();
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
		const payment_method = $( '#payment_method' ).val();

		switch ( payment_method ) {
		case 'cc':
			return true;
		default:
			return false;
		}
	}

	function loadScript( script_link, setup_callback ) {
		const scriptNode = document.createElement( 'script' );
		scriptNode.src = script_link;
		scriptNode.onload = setup_callback;
		scriptNode.onerror = function () {
			mw.donationInterface.validation.showErrors(
				{ general: 'Could not load payment provider Javascript. Please reload or try again later.' }
			);
		};
		document.body.append( scriptNode );
	}

	function simpleHash( str ) {
		let hash = 0;
		for ( let i = 0; i < str.length; i++ ) {
			const char = str.charCodeAt( i );
			// eslint-disable-next-line
			hash = ( hash << 5 ) - hash + char;
		}
		// Convert to 32bit unsigned integer in base 36 and pad with "0" to ensure length is 7.
		// eslint-disable-next-line
		return ( hash >>> 0 ).toString( 36 ).padStart( 7, '0' );
	}

	function setBinHash( clearBin ) {
		$( '#bin_hash' ).val( simpleHash( clearBin ) );
	}

	function elementIsVisible( inputElement ) {
		const style = window.getComputedStyle( inputElement );
		return style.display !== 'none' &&
		style.visibility !== 'hidden' &&
		style.opacity !== 0 &&
		inputElement.type !== 'hidden' &&
		inputElement.offsetParent !== null;
	}

	function focusOnFirstInput() {
		const inputFields = Array.from( document.querySelector( '#payment-form' ).querySelectorAll( 'input, select' ) );
		const visibleInputs = inputFields.filter( ( el ) => elementIsVisible( el ) );
		if ( visibleInputs.length > 0 ) {
			visibleInputs[ 0 ].focus();
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
		resetSubmethod: resetSubmethod,
		getOptIn: getOptIn,
		loadScript: loadScript,
		setBinHash: setBinHash,
		debugMessages: [],
		addDebugMessage: function ( message ) {
			di.forms.debugMessages.push( message );
		}
	};

	$( () => {

		const $emailDiv = $( '#email' ).closest( 'div' ),
			emailExplainMessage = mw.msg( 'donate_interface-email-explain' ),
			optInValue = mw.donationInterface.forms.getOptIn(),
			hasSetClientVariablesError = mw.config.get( 'DonationInterfaceSetClientVariablesError' );

		if ( hasSetClientVariablesError ) {
			location.assign( mw.config.get( 'DonationInterfaceFailUrl' ) );
			return;
		}

		focusOnFirstInput(); //T410787 T149803

		// If submethods are visible, and a submethod is already selected on
		// page load, clear it.
		if ( $( 'input[name="payment_submethod"]:checked:visible' ).length > 0 ) {
			di.forms.resetSubmethod();
		}

		// Submit on submethod click if valid, otherwise clear submethod selection.
		$( 'input[name="payment_submethod"]' ).on( 'click', () => {
			if ( di.validation.validate() ) {
				di.forms.submit();
			} else {
				di.forms.resetSubmethod();
				return false;
			}
		} );

		// Some forms show a 'continue' button when validation errors are found
		// server-side on the initial submit. When shown, it should validate
		// and submit the form.
		$( '#paymentContinueBtn' ).on( 'click', () => {
			if ( di.validation.validate() ) {
				di.forms.submit();
			}
		} );

		// Only load employer autocomplete js when the employer field is visible
		if ( $( '#employer' ).length ) {
			mw.loader.load( 'ext.donationInterface.employerAutoComplete' );
		}

		// Magic to hopefully disable the spinner in case we are returned to this
		// page via the Back button.
		$( window ).on(
			'unload', () => {
				// wrapped in case it is overwritten
				di.forms.enable();
				di.forms.enableInput();
			}
		);

		function showEmailExplain() {
			$emailDiv.after( '<div id="email_explain">' + emailExplainMessage + '</div>' );
		}

		if ( optInValue === '0' ) {
			showEmailExplain();
		}
	} );
} )( jQuery, mediaWiki );
