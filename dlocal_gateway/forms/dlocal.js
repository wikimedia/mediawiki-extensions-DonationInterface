/*global dlocal:true*/
( function ( $, mw ) {
	var country = $( '#country' ).val(), extraData = {},
		isRecurring = !!$( '#recurring' ).val();

	if ( country === 'IN' ) {
		$( '#fiscal_number' ).after(
			$( '<input type="hidden" value="Mumbai" name="city" id="city">' +
				'<p style="font-size: 10px">' + mw.msg( 'donate_interface-donor-fiscal_number-explain-in' ) +
				'</p>' )
		);
		if ( isRecurring  && mw.config.get( 'isOnDemand' ) ) {
			$( '.submethods' ).after( $( '<p>' +
				mw.msg( 'donate_interface-charge-monthly-only' ) +
				'</p>' ) );
		}
	}

	function handleApiResult( result ) {
		if ( result.isFailed ) {
			mw.donationInterface.validation.showErrors( {
				general: mw.msg( 'donate_interface-error-msg-general' )
			} );
		} else if ( result.redirect ) {
			document.location.replace( result.redirect );
		} else {
			document.location.replace( mw.config.get( 'DonationInterfaceThankYouPage' ) );
		}
	}

	if ( !mw.config.get( 'isDirectPaymentFlow' ) ) {
		$( '.submethods' ).after(
			$( '<p id="redirect-explanation">' + mw.message( 'donate_interface-redirect-explanation' ) + '</p>' )
		);
	} else {
		// Show our standard 'Donate' button
		$( '#paymentSubmit' ).show();
		// only non-recurring upi is direct, and it must IN and bt, so no needs to check those two val
		if ( $( 'input[name=payment_submethod]:checked' ).val() === 'upi' && !isRecurring ) {
			$( '.submethods' ).before(
				$( '<label for="upi_id">' +
					mw.msg( 'donate_interface-bt-upi_id' ) +
					'</label>' +
					'<input value="" name="upi_id" id="upi_id" />' ) );

			// Set the click handler
			$( '#paymentSubmitBtn' ).click(
				function ( event ) {
					event.preventDefault();
					// get verify
					extraData.fiscal_number = $( '#fiscal_number' ).val();
					extraData.upi_id = $( '#upi_id' ).val();
					mw.donationInterface.forms.callDonateApi(
						handleApiResult,
						extraData,
						'di_donate_dlocal'
					);
				}
			);
		}
	}

	function setup() {
		// only cc use setup
		if ( country === 'BR' && isRecurring ) {
			$( '.submethods' ).before( $( '<p>' +
				mw.msg( 'donate_interface-monthly-only-credit' ) +
				'</p>' ) );
		}

		var dlocalInstance = dlocal( mw.config.get( 'wgDlocalSmartFieldApiKey' ) );

		var fields = dlocalInstance.fields( {
			locale: mapLang( $( '#language' ).val() ),
			country: country
		} );

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

		// Custom styling can be passed to options when creating a Smart Field.
		var commonStyle = {
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

		var oldShowErrors = mw.donationInterface.validation.showErrors;
		mw.donationInterface.validation.showErrors = function ( errors ) {
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

		// create card field
		var card = fields.create( 'pan', {
			style: commonStyle,
			placeholder: '4111 1111 1111 1111'
		} );

		var cardFieldError = false;
		var cardFieldEmpty = true;

		card.addEventListener( 'change', function ( event ) {
			cardFieldError = !!event.error;
			if ( event.error ) {
				$( '#cardNumberErrorMsg' ).text( mw.msg( 'donate_interface-error-msg-invalid-card-number' ) );
			} else {
				$( '#cardNumberErrorMsg' ).text( '' );
			}
		} );

		card.on( 'blur', function ( event ) {
			cardFieldEmpty = event.empty;
		} );

		card.on( 'brand', function ( event ) {
			if ( event.brand ) {
				switch ( event.brand ) {
					case 'american-express':
						extraData.payment_submethod = 'amex';
						break;
					case 'mastercard':
						extraData.payment_submethod = 'mc';
						break;
					case 'diners-club':
						extraData.payment_submethod = 'diner';
						break;
					case 'hipercard':
						extraData.payment_submethod = 'hiper';
						break;
					case 'default': // dlocal can not find the corespondent card brand, so return error
						break;
					default:
						// like visa, elo, jcb, maestro, unionpay and discover
						extraData.payment_submethod = event.brand;
				}
			}
		} );
		var expiration = fields.create( 'expiration', {
			style: commonStyle,
			placeholder: mw.msg( 'donate_interface-expiry-date-field-placeholder' )
		} );

		var expirationFieldError = false;
		var expirationFieldEmpty = true;

		expiration.addEventListener( 'change', function ( event ) {
			expirationFieldError = !!event.error;
			if ( event.error ) {
				var message = mw.msg( 'donate_interface-error-msg-card-too-old' );
				$( '#expirationErrorMsg' ).text( message );
			} else {
				$( '#expirationErrorMsg' ).text( '' );
			}
		} );

		expiration.on( 'blur', function ( event ) {
			expirationFieldEmpty = event.empty;
		} );

		var cvv = fields.create( 'cvv', {
			style: commonStyle,
			placeholder: '123',
			maskInput: true
		} );

		var cvvFieldError = false;
		var cvvFieldEmpty = true;

		cvv.addEventListener( 'change', function ( event ) {
			cvvFieldError = !!event.error;
			if ( event.error ) {
				$( '#cvvErrorMsg' ).text( mw.msg( 'donate_interface-error-msg-invalid-cvv-format' ) );
			} else {
				$( '#cvvErrorMsg' ).text( '' );
			}
		} );

		cvv.on( 'blur', function ( event ) {
			cvvFieldEmpty = event.empty;
		} );

		function validateInputs() {
			var formValid = mw.donationInterface.validation.validate();
			var cvvFieldHasErrors = cvvFieldError || cvvFieldEmpty;
			var cardFieldHasErrors = cardFieldError || cardFieldEmpty;
			var expFieldHasErrors = expirationFieldError || expirationFieldEmpty;
			if ( !formValid || cvvFieldHasErrors || cardFieldHasErrors || expFieldHasErrors ) {
				var errors = {};
				if ( cardFieldHasErrors ) {
					if ( cardFieldEmpty ) {
						errors.cardNumber = mw.msg( 'donate_interface-error-msg-card-num' );
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

		// Set the click handler
		$( '#paymentSubmitBtn' ).click(
			function ( event ) {
				event.preventDefault();
				var inputFieldsAreValid = validateInputs();

				if ( inputFieldsAreValid ) {
					dlocalInstance.createToken( card, {
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
		);

		// Drop in the dlocal card components placeholder
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

		card.mount( document.getElementById( 'cardNumber' ) );
		expiration.mount( document.getElementById( 'expiration' ) );
		cvv.mount( document.getElementById( 'cvv' ) );
	}

	/**
	 *  On document ready we create a script tag and wire it up to run setup as soon as it
	 *  is loaded, or to show an error message if the external script can't be loaded.
	 *  The script should already be mostly or completely preloaded at this point, thanks
	 *  to a <link rel=preload> we add in DlocalGateway::execute
	 */
	$( function () {
		// only cc load smart field script and submit button, others show redirect with continue button
		if ( $( '#payment_method' ).val() === 'cc' ) {
			var scriptNode = document.createElement( 'script' );
			scriptNode.onload = setup;
			scriptNode.onerror = function () {
				mw.donationInterface.validation.showErrors(
					{ general: 'Could not load payment provider Javascript. Please reload or try again later.' }
				);
			};
			scriptNode.src = mw.config.get( 'dlocalScript' );
			document.body.append( scriptNode );
		}
	} );
} )( jQuery, mediaWiki );
