/*global dlocal:true*/
( function ( $, mw ) {
	var country = $( '#country' ).val(),
		extraData = {},
		isRecurring = !!$( '#recurring' ).val(),
		isIndia = ( country === 'IN' );

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

	function showPaymentSubmit() {
		$( '#paymentSubmit' ).show();
	}

	function setupCardForm() {
		var dlocalInstance = dlocal( mw.config.get( 'wgDlocalSmartFieldApiKey' ) ),
			fields = dlocalInstance.fields( {
				locale: mapLang( $( '#language' ).val() ),
				country: country
			} ),
			// Custom styling can be passed to options when creating a Smart Field.
			commonStyle = getCardCommonStyle(),
			cardField,
			cardFieldError = false,
			cardFieldSupportError = false,
			cardFieldEmpty = true,
			expirationField,
			expirationFieldError = false,
			expirationFieldEmpty = true,
			cvvField,
			cvvFieldError = false,
			cvvFieldEmpty = true;

		insertBrazilRecurringAdvice();
		addCardFieldsToErrorDisplay();

		// create card fields and add events
		cardField = fields.create( 'pan', {
			style: commonStyle,
			placeholder: '4111 1111 1111 1111'
		} );
		addCardFieldEvents();

		expirationField = fields.create( 'expiration', {
			style: commonStyle,
			placeholder: mw.msg( 'donate_interface-expiry-date-field-placeholder' )
		} );
		addExpirationFieldEvents();

		cvvField = fields.create( 'cvv', {
			style: commonStyle,
			placeholder: '123',
			maskInput: true
		} );
		addCvvFieldEvents();

		// Show our standard 'Donate' button
		showPaymentSubmit();
		// Set the click handler
		// Using debounce to prevent the programmatic trigger of multiple instances of the submit function
		$( '#paymentSubmitBtn' ).click( mw.util.debounce( handleCardSubmitClick, 100 ) );

		// Drop in the dlocal card components placeholder
		insertCardComponentContainers();

		cardField.mount( document.getElementById( 'cardNumber' ) );
		expirationField.mount( document.getElementById( 'expiration' ) );
		cvvField.mount( document.getElementById( 'cvv' ) );

		function addCardFieldEvents() {
			cardField.addEventListener( 'change', function ( event ) {
				cardFieldError = !!event.error;
				if ( event.error ) {
					$( '#cardNumberErrorMsg' ).text( mw.msg( 'donate_interface-error-msg-unsupported-card-entered' ) );
				} else {
					$( '#cardNumberErrorMsg' ).text( '' );
				}
			} );

			cardField.on( 'blur', function ( event ) {
				cardFieldEmpty = event.empty;
			} );

			cardField.on( 'brand', function ( event ) {
				// after input 6 number, ready to check bin
				if ( event.brand ) {
					dlocalInstance.getBinInformation( cardField ).then( function ( res ) {
						var binInfoCardBrand = res.brand;
						var cardBrand = mw.config.get( 'codeMap' )[ binInfoCardBrand ];
						if ( cardBrand !== undefined ) {
							cardFieldSupportError = false;
							extraData.payment_submethod = cardBrand;
							$( '#cardNumberErrorMsg' ).text( '' );
						} else {
							cardFieldSupportError = true;
							$( '#credit-card-wrapper' ).addClass( 'DlocalField--invalid' );
							$( '#cardNumberErrorMsg' ).text( mw.msg( 'donate_interface-error-msg-unsupported-card-entered' ) );
						}
					} ).catch( function ( error ) {
						// Suppress bin lookup error.
					} );
				}
			} );
		}

		function addExpirationFieldEvents() {
			expirationField.addEventListener( 'change', function ( event ) {
				expirationFieldError = !!event.error;
				if ( event.error ) {
					$( '#expirationErrorMsg' ).text( mw.msg( 'donate_interface-error-msg-card-too-old' ) );
				} else {
					$( '#expirationErrorMsg' ).text( '' );
				}
			} );

			expirationField.on( 'blur', function ( event ) {
				expirationFieldEmpty = event.empty;
			} );
		}

		function addCvvFieldEvents() {
			cvvField.addEventListener( 'change', function ( event ) {
				cvvFieldError = !!event.error;
				if ( event.error ) {
					$( '#cvvErrorMsg' ).text( mw.msg( 'donate_interface-error-msg-invalid-cvv-format' ) );
				} else {
					$( '#cvvErrorMsg' ).text( '' );
				}
			} );

			cvvField.on( 'blur', function ( event ) {
				cvvFieldEmpty = event.empty;
			} );
		}

		function validateInputs() {
			var formValid = mw.donationInterface.validation.validate(),
				cvvFieldHasErrors = cvvFieldError || cvvFieldEmpty,
				cardFieldHasErrors = cardFieldError || cardFieldEmpty || cardFieldSupportError,
				expFieldHasErrors = expirationFieldError || expirationFieldEmpty,
				errors = {};

			if ( !formValid || cvvFieldHasErrors || cardFieldHasErrors || expFieldHasErrors ) {
				if ( cardFieldHasErrors ) {
					if ( cardFieldEmpty ) {
						errors.cardNumber = mw.msg( 'donate_interface-error-msg-card-num' );
					} else if ( cardFieldSupportError ) {
						errors.cardNumber = mw.msg( 'donate_interface-error-msg-unsupported-card-entered' );
					} else {
						errors.cardNumber = mw.msg( 'donate_interface-error-msg-invalid-card-number' );
					}
				}
				if ( cvvFieldHasErrors ) {
					if ( cvvFieldEmpty ) {
						errors.cvv = mw.msg( 'donate_interface-error-msg-cvv' );
					} else {
						errors.cvv = mw.msg( 'donate_interface-error-msg-invalid-cvv-format' );
					}
				}
				if ( expFieldHasErrors ) {
					if ( expirationFieldEmpty ) {
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
				dlocalInstance.createToken( cardField, {
					name: $( '#first_name' ).val() + ' ' + $( '#last_name' ).val()
				} ).then( function ( result ) {
					// Send the token to your server.
					extraData.fiscal_number = $( '#fiscal_number' ).val();
					extraData.payment_token = result.token;
					mw.donationInterface.forms.callDonateApi(
						handleApiResult,
						extraData,
						'di_donate_dlocal'
					);
				} ).catch( function ( result ) {
					if ( result.error ) {
						mw.donationInterface.validation.showErrors( {
							general: mw.msg( 'donate_interface-error-msg-general' )
						} );
					}
				} );
			}
		}
	}

	// Helper functions for card form setup that do not need to be inside the setup function's scope
	function insertBrazilRecurringAdvice() {
		if ( country === 'BR' && isRecurring ) {
			$( '.submethods' ).before( $( '<p>' +
				mw.msg( 'donate_interface-monthly-only-credit' ) +
				'</p>' ) );
		}
	}

	// https://docs.dlocal.com/reference/the-dlocal-object#dlocalfieldsoptions
	// Supported values are: es, en, pt, zh, cv, tr.
	function mapLang( wikiLang ) {
		if ( wikiLang === 'es-419' ) {
			return 'es';
		} else if ( [ 'es', 'en', 'pt', 'zh', 'cv', 'tr' ].indexOf( wikiLang ) !== -1 ) {
			return wikiLang;
		} else {
			// todo: maybe display an error, or just default en?
			return 'en';
		}
	}

	function addCardFieldsToErrorDisplay() {
		var oldShowErrors = mw.donationInterface.validation.showErrors;
		mw.donationInterface.validation.showErrors = function ( errors ) {
			mw.donationInterface.forms.enable();
			var dLocalFields = [ 'cardNumber', 'expiration', 'cvv' ];
			$.each( errors, function ( field ) {
				if ( dLocalFields.indexOf( field ) !== -1 ) {
					$( '#' + field ).find( '.DlocalField' ).addClass( 'DlocalField--invalid' );
					$( '#' + field + 'ErrorMsg' ).text( errors[ field ] );
					delete errors[ field ];
				}
			} );
			oldShowErrors( errors );
		};
	}

	function insertCardComponentContainers() {
		$( '.submethods' ).before(
			'<div>' +
			'<label for="cardNumber">' + mw.message( 'donate_interface-donor-card-num' ) + '</label>' +
			'<div id="cardNumber" ></div>' +
			'<span class="DlocalField--invalid-text" id="cardNumberErrorMsg" />' +
			'</div>' +
			'<div>' +
			'<div class="halfwidth">' +
			'<label for="expiration">' + mw.message( 'donate_interface-donor-expiration' ) + '</label>' +
			'<div id="expiration" ></div>' +
			'<span class="DlocalField--invalid-text" id="expirationErrorMsg"></span>' +
			'</div>' +
			'<div class="halfwidth">' +
			'<label for="cvv">' + mw.message( 'donate_interface-cvv' ) + '</label>' +
			'<div id="cvv"></div>' +
			'<span class="DlocalField--invalid-text" id="cvvErrorMsg"></span>' +
			'</div>' +
			'</div>'
		);
	}

	function getCardCommonStyle() {
		return {
			base: {
				fontSize: '14px',
					fontFamily: 'sans-serif',
					lineHeight: '40px',
					fontSmoothing: 'antialiased',
					fontWeight: '500',
					color: 'rgb(0, 17, 44)',
					'::placeholder': {
					color: 'rgb(185, 196, 201)'
				}
			},
			focus: {
				iconColor: '#adbfd3',
					'::placeholder': {
					color: '#adbfd3'
				}
			},
			autofilled: {
				color: '#000000'
			},
			invalid: {
				color: '#f00'
			}
		};
	}

	function setupNonCardForm() {
		var upiRecurringIsOnDemand = mw.config.get( 'isOnDemand' ),
			isDirectPaymentFlow = mw.config.get( 'isDirectPaymentFlow' ),
			// 'paytmwallet' submethod should be treated as the same for upi
			isUpi = new RegExp( '\\b' + $( 'input[name=payment_submethod]:checked' ).val() + '\\b', 'i' ).test( 'upi paytmwallet' ); // i is case insensitive

		if ( isUpi && isRecurring && upiRecurringIsOnDemand ) {
			// If we are using the ONDEMAND charge frequency, add a note to reassure donors
			// that we will only charge them once a month
			$( '.submethods' ).after( $( '<p>' +
				mw.msg( 'donate_interface-charge-monthly-only' ) +
				'</p>' ) );
		}

		if ( isDirectPaymentFlow ) {
			// Show our standard 'Donate' button
			showPaymentSubmit();
			// only non-recurring upi is direct, and it must IN and bt, so no needs to check those two val
			if ( isUpi && !isRecurring ) {
				addUpiDirectFlowInputField();
				// Set the click handler
				$( '#paymentSubmitBtn' ).click( mw.util.debounce( handleUpiDirectSubmitClick, 100 ) );
			}
		} else {
			// Redirect flow
			$( '.submethods' ).after(
				$( '<p id="redirect-explanation">' + mw.message( 'donate_interface-redirect-explanation' ) + '</p>' )
			);
			if ( isUpi && !isRecurring ) {
				// Redirected one-time UPI payments have an optional phone number field. Add an explanation.
				$( '#phone' ).after(
					$( '<p class="explanation">' + mw.message( 'donate_interface-donor-phone-explain-in' ) + '</p>' )
				);
			}
		}
	}

	// Support functions for non-card form setup
	function addUpiDirectFlowInputField() {
		$( '.submethods' ).before(
			$( '<label for="upi_id">' +
				mw.msg( 'donate_interface-bt-upi_id' ) +
				'</label>' +
				'<input value="" name="upi_id" id="upi_id" />' ) );
	}

	function handleUpiDirectSubmitClick( event ) {
		if ( $( this ).is( ':disabled' ) ) {
			return;
		}
		event.preventDefault();
		// Disable submit btn when submitting
		mw.donationInterface.forms.disable();
		// get verify
		extraData.fiscal_number = $( '#fiscal_number' ).val();
		extraData.upi_id = $( '#upi_id' ).val();
		mw.donationInterface.forms.callDonateApi(
			handleApiResult,
			extraData,
			'di_donate_dlocal'
		);
	}

	/**
	 *  On document ready we create a script tag and wire it up to run setup as soon as it
	 *  is loaded, or to show an error message if the external script can't be loaded.
	 *  The script should already be mostly or completely preloaded at this point, thanks
	 *  to a <link rel=preload> we add in DlocalGateway::execute
	 */
	$( function () {
		if ( isIndia ) {
			$( '#fiscal_number' ).after(
				$( '<p style="font-size: 10px">' + mw.msg( 'donate_interface-donor-fiscal_number-explain-option-in' ) +
					'</p>' )
			);
		}
		// only cc load smart field script and submit button, others show redirect with continue button
		if ( $( '#payment_method' ).val() === 'cc' ) {
			var scriptNode = document.createElement( 'script' );
			scriptNode.onload = setupCardForm;
			scriptNode.onerror = function () {
				mw.donationInterface.validation.showErrors(
					{ general: 'Could not load payment provider Javascript. Please reload or try again later.' }
				);
			};
			scriptNode.src = mw.config.get( 'dlocalScript' );
			document.body.append( scriptNode );
		} else {
			setupNonCardForm();
		}
	} );
} )( jQuery, mediaWiki );
