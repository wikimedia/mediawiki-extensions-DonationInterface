/* global SecureFields */
( function ( $, mw ) {
	var secureFieldValid = false,
	cardNumberFieldValid = false,
	securityCodeValid = false,
	expiryDateValid = false,
	cardNumberFieldEmpty = true,
	securityCodeFieldEmpty = true,
	expiryDateFieldEmpty = true,
	secureFields = null,
	sessionId = mw.config.get( 'gravy_session_id' ),
	environment = mw.config.get( 'wgGravyEnvironment' ),
	gravyId = mw.config.get( 'wgGravyId' );

	function insertCardComponentContainers() {
		$( '.submethods' ).before(
			'<div>' +
			'<label for="cc-number">' + mw.message( 'donate_interface-donor-card-num' ) + '</label>' +
			'<input id="cc-number" />' +
			'<span class="GravyField--invalid-text" id="cardNumberErrorMsg" />' +
			'</div>' +
			'<div>' +
			'<div class="halfwidth">' +
			'<label for="cc-expiry-date">' + mw.message( 'donate_interface-donor-expiration' ) + '</label>' +
			'<input id="cc-expiry-date" />' +
			'<span class="GravyField--invalid-text" id="expirationErrorMsg"></span>' +
			'</div>' +
			'<div class="halfwidth">' +
			'<label for="cc-security-code">' + mw.message( 'donate_interface-cvv' ) + '</label>' +
			'<input id="cc-security-code" />' +
			'<span class="GravyField--invalid-text" id="cvvErrorMsg"></span>' +
			'</div>' +
			'</div>'
		);
	}

	function setupCardFields() {
			secureFields.addCardNumberField( '#cc-number', {
				placeholder: '1234 5678 9012 3456',
				styles: {
					fontSize: '16px'
				}
			} );

			secureFields.addSecurityCodeField( '#cc-security-code', {
				placeholder: mw.msg( 'donate_interface-cvv' ),
				styles: {
					fontSize: '16px'
				}
				}
			);

			secureFields.addExpiryDateField( '#cc-expiry-date', {
				placeholder: mw.msg( 'donate_interface-expiry-date-field-placeholder' ),
				styles: {
					fontSize: '16px'
				}
				}
			);

	}

	function setupCardForm() {
		secureFields = new SecureFields( {
			gr4vyId: gravyId,
			environment: environment,
			sessionId: sessionId,
			styles: {
				fontSize: '16px',
				padding: '0.8em',
				backgroundColor: '#fff',
				borderRadius: '2px',
				border: '1px solid #a2a9b1',
				color: '#000',
				fontFamily: 'inherit',
				lineHeight: '17px',
				marginBottom: '0.2em'
			},
			theme: {
				colors: {
					inputBorder: '#a2a9b1'
				}
			}
		} );

		secureFields.addEventListener(
			SecureFields.Events.FORM_CHANGE,
			function ( data ) {
				if ( data ) {
					secureFieldValid = data.complete;
					if ( data.fields ) {
						cardNumberFieldEmpty = data.fields.number.empty;
						cardNumberFieldValid = !cardNumberFieldEmpty && data.fields.number.valid;
						expiryDateFieldEmpty = data.fields.expiryDate.empty;
						expiryDateValid = !expiryDateFieldEmpty && data.fields.expiryDate.valid;
						securityCodeFieldEmpty = data.fields.securityCode.empty;
						securityCodeValid = !securityCodeFieldEmpty && data.fields.securityCode.valid;
					}
				}
			}
		);

		insertCardComponentContainers();
		setupCardFields();

		$( '#paymentSubmit' ).show();
		$( '#paymentSubmitBtn' ).click( mw.util.debounce( handleCardSubmitClick, 100 ) );
	}

	function validateInputs() {
		var formValid = mw.donationInterface.validation.validate(),
			errors = {};

		if ( !formValid || !secureFieldValid ) {
			if ( !cardNumberFieldValid ) {
				if ( cardNumberFieldEmpty ) {
					errors.cardNumber = mw.msg( 'donate_interface-error-msg-card-num' );
				} else {
					errors.cardNumber = mw.msg( 'donate_interface-error-msg-invalid-card-number' );
				}
			}
			if ( !securityCodeValid ) {
				if ( securityCodeFieldEmpty ) {
					errors.cvv = mw.msg( 'donate_interface-error-msg-cvv' );
				} else {
					errors.cvv = mw.msg( 'donate_interface-error-msg-invalid-cvv-format' );
				}
			}
			if ( !expiryDateValid ) {
				if ( expiryDateFieldEmpty ) {
					errors.expiration = mw.msg( 'donate_interface-error-msg-expiration' );
				} else {
					errors.expiration = mw.msg( 'donate_interface-error-msg-card-too-old' );
				}
			}
			mw.donationInterface.validation.showErrors( errors );
			return false;
		}
		return true;
	}

	function handleCardSubmitClick( event ) {
		if ( $( this ).is( ':disabled' ) ) {
			return;
		}
		event.preventDefault();
		mw.donationInterface.forms.disable();

		if ( validateInputs() ) {
			secureFields.submit();
			mw.donationInterface.forms.callDonateApi(
				handleApiResult,
				{
					gateway_session_id: sessionId
				},
				'di_donate_gravy'
			);
		} else {
			mw.donationInterface.forms.enable();
		}
	}

	function handleApiResult( result ) {
		if ( result.isFailed ) {
			mw.donationInterface.validation.showErrors( {
				general: mw.msg( 'donate_interface-error-msg-general' )
			} );
		} else if ( mw.monthlyConvert && mw.monthlyConvert.canShowModal() ) {
			mw.monthlyConvert.init();
		} else if ( result.redirect ) {
			document.location.replace( result.redirect );
		} else {
			document.location.replace( mw.config.get( 'DonationInterfaceThankYouPage' ) );
		}
	}
	/**
	 *  On document ready we create a script tag and wire it up to run setup as soon as it
	 *  is loaded, or to show an error message if the external script can't be loaded.
	 *  The script should already be mostly or completely preloaded at this point, thanks
	 *  to a <link rel=preload> we add in GravyGateway::execute
	 */
	$( function () {
		if ( $( '#payment_method' ).val() === 'cc' ) {
			var scriptNode = document.createElement( 'script' );
			scriptNode.onload = setupCardForm;
			scriptNode.onerror = function () {
				mw.donationInterface.validation.showErrors(
					{ general: 'Could not load payment provider Javascript. Please reload or try again later.' }
				);
			};
			scriptNode.src = mw.config.get( 'secureFieldsScriptLink' );
			document.body.append( scriptNode );

		}
	} );
} )( jQuery, mediaWiki );
