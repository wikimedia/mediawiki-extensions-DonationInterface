/* global SecureFields google ApplePaySession */
( function ( $, mw ) {
	var secureFieldValid = false,
	cardNumberFieldValid = false,
	securityCodeValid = false,
	expiryDateValid = false,
	cardNumberFieldEmpty = true,
	securityCodeFieldEmpty = true,
	expiryDateFieldEmpty = true,
	secureFields = null,
	extraData = {},
	configFromServer = mw.config.get( 'gravyConfiguration' ),
	sessionId = mw.config.get( 'gravy_session_id' ),
	environment = mw.config.get( 'wgGravyEnvironment' ),
	gravyId = mw.config.get( 'wgGravyId' ),
	redirectPaypal = mw.config.get( 'wgGravyRedirectPaypal' ),
	googlePaymentClient = null,
	appleSession = null,
	language = $( '#language' ).val(),
	applePayPaySessionVersionNumber = 3; // https://developer.apple.com/documentation/apple_pay_on_the_web/apple_pay_on_the_web_version_history

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
			'<label for="cc-security-code">' + mw.message( 'donate_interface-donor-security' ) + '</label>' +
			'<input id="cc-security-code" />' +
			'<span class="GravyField--invalid-text" id="cvvErrorMsg"></span>' +
			'</div>' +
			'</div>'
		);
	}

	function ccInputEmptyStyle( id, evt ) {
		if ( !evt.empty ) {
			setFieldError( id ,evt.valid, false );
		} else {
			$( id ).removeAttr( 'data-secure-fields-invalid' );
		}
	}

	function setupCardFields() {
		var inputStyle = {
			fontSize: '16px',
			padding: '5px 8px',
			invalidColor: 'unset'
		};
		var cardNumberField = secureFields.addCardNumberField( '#cc-number', {
				placeholder: '1234 5678 9012 3456',
				styles: inputStyle
			} );

		var securityCodeField = secureFields.addSecurityCodeField( '#cc-security-code', {
				placeholder: mw.msg( 'donate_interface-cvv-placeholder-3-digits' ),
				styles: inputStyle
			} );

		var expiryDateField = secureFields.addExpiryDateField( '#cc-expiry-date', {
				placeholder: mw.msg( 'donate_interface-expiry-date-field-placeholder' ),
				styles: inputStyle
			} );
		// based on card type show logo and update cvv placeholder when amex
		cardNumberField.addEventListener( 'input', function ( evt ) {
			if ( evt.schema ) {
				//change logo where appropriate
				var iconUrl = 'https://api.' + gravyId + '.gr4vy.app/assets/icons/card-schemes/' + evt.schema + '.svg';
				$( '#cc-number' ).css( 'background-image', 'url(' + iconUrl + ')' );
				if ( evt.schema === 'amex' ) {
					securityCodeField.setPlaceholder( mw.msg( 'donate_interface-cvv-placeholder-4-digits' ) );
				} else {
					securityCodeField.setPlaceholder( mw.msg( 'donate_interface-cvv-placeholder-3-digits' ) );
				}
			}
			ccInputEmptyStyle( '#cc-number', evt );
		} );
		expiryDateField.addEventListener( 'input', function ( evt ) {
			// for error icon
			$( '#cc-expiry-date' ).toggleClass( 'valid-input', !evt.empty && evt.valid );
			ccInputEmptyStyle( '#cc-expiry-date', evt );
		} );
		securityCodeField.addEventListener( 'input', function ( evt ) {
			// for error icon
			$( '#cc-security-code' ).toggleClass( 'valid-input', !evt.empty && evt.valid );
			ccInputEmptyStyle( '#cc-security-code', evt );
		} );
	}

	function setFieldError( fieldId, isValid, isEmpty ) {
		var errorMsg = '', errorMsgId, errorMsgKey, emptyMsgKey;
		switch ( fieldId ) {
			case '#cc-number':
				errorMsgId = '#cardNumberErrorMsg';
				errorMsgKey = 'donate_interface-error-msg-invalid-card-number';
				emptyMsgKey = 'donate_interface-error-msg-card-num';
				break;
			case '#cc-expiry-date':
				errorMsgId = '#expirationErrorMsg';
				errorMsgKey = 'donate_interface-error-msg-expiry-date-field-invalid';
				emptyMsgKey = 'donate_interface-error-msg-expiration';
				break;
			case '#cc-security-code':
				errorMsgId = '#cvvErrorMsg';
				errorMsgKey = 'donate_interface-error-msg-invalid-cvv-format';
				emptyMsgKey = 'donate_interface-error-msg-cvv';
				break;
		}
		$( fieldId ).toggleClass( 'GravyField--invalid invalid-input', !isValid || isEmpty );
		if ( !isValid || isEmpty ) {
			errorMsg = isEmpty ? mw.msg( emptyMsgKey ) : mw.msg( errorMsgKey );
		}
		$( errorMsgId ).text( errorMsg );
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

		secureFields.addEventListener( SecureFields.Events.CARD_VAULT_SUCCESS, function () {
			extraData.fiscal_number = $( '#fiscal_number' ).val();
			extraData.gateway_session_id = sessionId;
			mw.donationInterface.forms.callDonateApi(
				handleApiResult,
				extraData,
				'di_donate_gravy'
			);
		} );

		secureFields.addEventListener(
			SecureFields.Events.CARD_VAULT_FAILURE,
			function () {
				mw.donationInterface.forms.addDebugMessage( 'Card vault failure on gravy checkout session id: ' + sessionId );
				mw.donationInterface.validation.showErrors( {
					general: mw.msg( 'donate_interface-error-msg-general' )
				} );
				mw.donationInterface.forms.enable();
			}
		);

		secureFields.addEventListener(
			SecureFields.Events.FORM_CHANGE,
			function ( data ) {
				if ( data ) {
					secureFieldValid = data.complete;
					if ( data.fields ) {
						cardNumberFieldEmpty = data.fields.number.empty;
						cardNumberFieldValid = data.fields.number.valid;
						expiryDateFieldEmpty = data.fields.expiryDate.empty;
						expiryDateValid = data.fields.expiryDate.valid;
						securityCodeFieldEmpty = data.fields.securityCode.empty;
						securityCodeValid = data.fields.securityCode.valid;
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
		if ( !mw.donationInterface.validation.validate() || !secureFieldValid ) {
			setFieldError( '#cc-number',  cardNumberFieldValid, cardNumberFieldEmpty );
			setFieldError( '#cc-security-code',  securityCodeValid, securityCodeFieldEmpty );
			setFieldError( '#cc-expiry-date',  expiryDateValid, expiryDateFieldEmpty );
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
		} else {
			mw.donationInterface.forms.enable();
		}
	}

	function handleApiResult( result ) {
		if ( result.isFailed ) {
			mw.donationInterface.validation.showErrors( {
				general: mw.msg( 'donate_interface-error-msg-general' )
			} );
		} else if ( result.redirect ) {
			document.location.replace( result.redirect );
		} else if ( mw.monthlyConvert && mw.monthlyConvert.canShowModal() ) {
			mw.monthlyConvert.init();
		} else {
			document.location.replace( mw.config.get( 'DonationInterfaceThankYouPage' ) );
		}
	}

	function insertGooglePayComponentContainer() {
		$( '.submethods' ).before(
			'<div id="container">' +
			'</div>'
		);
	}

	function handleGooglePayButtonClick() {
		var paymentRequest = getGooglepayRequest();
		var googlePayClient = getGooglePayClient();
		googlePayClient.loadPaymentData( paymentRequest ).then( function ( paymentData ) {
			var paymentToken = paymentData.paymentMethodData.tokenizationData.token;
			var donorInfo = paymentData.paymentMethodData.info.billingAddress,
							extraData = {};
			extraData.postal_code = donorInfo.postalCode;
			extraData.state_province = donorInfo.administrativeArea;
			extraData.city = donorInfo.locality;
			extraData.street_address = donorInfo.address1;
			extraData.email = paymentData.email;
			extraData.full_name = donorInfo.name;
			extraData.payment_token = paymentToken;
			extraData.card_suffix = paymentData.paymentMethodData.info.cardDetails;
			extraData.card_scheme = paymentData.paymentMethodData.info.cardNetwork;
			mw.donationInterface.forms.callDonateApi(
				handleApiResult,
				extraData,
				'di_donate_gravy'
			);
		} ).catch( function ( err ) {
			mw.donationInterface.forms.addDebugMessage( 'Google Pay failure: ' + err );
			mw.donationInterface.validation.showErrors( {
					general: mw.msg( 'donate_interface-error-msg-general' )
			} );
		} );
	}

	function getGoogleBaseRequest() {
		return {
			apiVersion: 2,
			apiVersionMinor: 0
		};
	}

	function getGoogleBaseCardPaymentMethod() {
		var allowedCardNetworks = configFromServer.googleAllowedNetworks;
		var allowedCardAuthMethods = [ 'PAN_ONLY', 'CRYPTOGRAM_3DS' ];
		var baseCardPaymentMethod = {
			type: 'CARD',
			parameters: {
				allowedCardNetworks: allowedCardNetworks,
				allowedAuthMethods: allowedCardAuthMethods,
				billingAddressRequired: true,
				billingAddressParameters: {
					format: 'FULL'
				}
			}
		};
		return baseCardPaymentMethod;
	}

	function getGoogleTransactionInfo() {
		return {
			totalPriceStatus: 'FINAL',
			totalPrice: $( '#amount' ).val(),
			currencyCode: $( '#currency' ).val(),
			countryCode: $( '#country' ).val()
		};
	}

	function getGoogleMerchantInfo() {
		return {
			merchantName: 'WikimediaFoundation',
			merchantId: configFromServer.googleMerchantId
		};
	}

	function getGooglepayRequest() {
		var paymentRequest = getGoogleBaseRequest();
		var cardPaymentMethod = getGoogleBaseCardPaymentMethod();
		var gravyGooglePayMerchantId = configFromServer.gravyGooglePayMerchantId;
		var tokenizationSpecification = {
			type: 'PAYMENT_GATEWAY',
			parameters: {
				gateway: 'gr4vy',
				gatewayMerchantId: gravyGooglePayMerchantId
			}
		};
		cardPaymentMethod.tokenizationSpecification = tokenizationSpecification;
		paymentRequest.allowedPaymentMethods = [ cardPaymentMethod ];
		paymentRequest.transactionInfo = getGoogleTransactionInfo();
		paymentRequest.merchantInfo = getGoogleMerchantInfo();

		paymentRequest.emailRequired = true;

		return paymentRequest;
	}

	function getGoogleIsReadyToPayRequest() {
		var request = getGoogleBaseRequest();
		var baseCardPaymentMethod = getGoogleBaseCardPaymentMethod();
		request.allowedPaymentMethods = [ baseCardPaymentMethod ];
		return request;
	}

	function getGooglePayClient() {
		if ( googlePaymentClient === null ) {
			return new google.payments.api.PaymentsClient( { environment: configFromServer.googleEnvironment } );
		}
		return googlePaymentClient;
	}

	function setupGooglePayForm() {
		insertGooglePayComponentContainer();
		var googlePayClient = getGooglePayClient();
		var isReadyToPayRequest = getGoogleIsReadyToPayRequest();
		googlePayClient
			.isReadyToPay( isReadyToPayRequest )
			.then( function ( response ) {
				if ( response.result ) {
					var button = googlePayClient.createButton( {
						onClick: handleGooglePayButtonClick,
						allowedPaymentMethods: [ 'CARD','TOKENIZED_CARD' ],
						buttonType: 'donate'
					} );
					document.getElementById( 'container' ).appendChild( button );
				}
			} )
			.catch( function ( err ) {
				mw.donationInterface.forms.addDebugMessage( 'Google Pay failure: ' + err );
			} );
	}

	function setupApplePayForm() {
		// Check apple pay availability before showing button
		if ( window.ApplePaySession ) {
			insertApplePayComponentContainer();
			var button = document.getElementById( 'applepay-btn' );
			button.addEventListener( 'click', handleApplePaySubmitClick );
		} else {
			mw.donationInterface.validation.showErrors( {
				general: mw.message(
					'donate_interface-error-msg-apple_pay_unsupported',
					mw.config.get( 'DonationInterfaceOtherWaysURL' )
				).plain()
			} );
			mw.donationInterface.forms.addDebugMessage( 'Apple Pay failure: Unable to find ApplePaySession in browser' );
		}
	}

	function handleApplePayApiResult( result ) {
		appleSession.completePayment( {
			status: ApplePaySession.STATUS_SUCCESS
		} );

		handleApiResult( result );
	}

	function handleApplePaySubmitClick( e ) {
		e.preventDefault();
		setupApplePaySession();
		appleSession.begin();
	}

	function validateApplePayPaymentSession( appleSession ) {
		return function ( event ) {
			var api = new mw.Api();
			api.post( {
				action: 'di_applesession_gravy',
				validation_url: event.validationURL,
				wmf_token: $( '#wmf_token' ).val()
			} ).then( function ( data ) {
				if ( data.result && data.result.errors ) {
					mw.donationInterface.validation.showErrors( {
						general: mw.msg( 'donate_interface-error-msg-general' )
					} );
					mw.donationInterface.forms.addDebugMessage( 'Apple Pay failure: ' + data.result.errors );
				} else {
					appleSession.completeMerchantValidation( data.session );
				}
			} ).catch( function ( e ) {
				mw.donationInterface.forms.addDebugMessage( 'Apple Pay failure: ' + e );
				mw.donationInterface.validation.showErrors( {
					general: mw.msg( 'donate_interface-error-msg-general' )
				} );
			} );
		};
	}

	function setupApplePaySession() {
		var paymentRequestObject = {
			countryCode: $( '#country' ).val(),
			currencyCode: $( '#currency' ).val(),
			merchantCapabilities: [ 'supportsCredit', 'supportsDebit', 'supports3DS' ],
			supportedNetworks: [ 'visa', 'masterCard', 'amex', 'discover' ],
			requiredBillingContactFields: [ 'email', 'name', 'phone', 'postalAddress' ],
			requiredShippingContactFields: [ 'email', 'name' ],
			total: {
				label: 'Wikimedia Foundation',
				type: 'final',
				amount: $( '#amount' ).val()
			}
		};
		appleSession = new ApplePaySession( applePayPaySessionVersionNumber, paymentRequestObject );

		appleSession.onvalidatemerchant = validateApplePayPaymentSession( appleSession );

		appleSession.onpaymentauthorized = function ( event ) {
			var bContact = event.payment.billingContact,
				sContact = event.payment.shippingContact;
			var extraData = {};
			var paymentSubmethod = event.payment.token.paymentMethod.network;
			if ( !paymentSubmethod ) {
				paymentSubmethod = '';
			}
			extraData = mw.donationInterface.forms.apple.getBestApplePayContactName( extraData, bContact, sContact );
			extraData.postal_code = bContact.postalCode;
			extraData.state_province = bContact.administrativeArea;
			extraData.city = bContact.locality;
			if ( bContact.addressLines.length > 0 ) {
				extraData.street_address = bContact.addressLines[ 0 ];
			}
			extraData.email = sContact.emailAddress;
			extraData.payment_submethod = paymentSubmethod.toLowerCase();
			extraData.payment_token = JSON.stringify( event.payment.token );
			mw.donationInterface.forms.callDonateApi(
				handleApplePayApiResult,
				extraData,
				'di_donate_gravy'
			);
		};
	}

	function insertApplePayComponentContainer() {
		$( '.submethods' ).before(
			'<div id="container">' +
			'<apple-pay-button class="button" id="applepay-btn" buttonstyle="black" type="donate" locale="' + language + '"></apple-pay-button>' +
			'</div>'
		);
	}

	function submitPaypal() {
		var di = mw.donationInterface;

		function redirect( result ) {
			// We don't actually want to enable the form on redirect or in the
			// complete phase of callDonateApi, so we override enable here.
			di.forms.enable = function () {};
			location.assign( result.redirect );
		}

		di.forms.submit = function () {
			// MediaWiki uses the "uselang" parameter to set the language for localization
			// Checkout /payments/includes/api/ApiMain.php
			di.forms.callDonateApi( redirect, { uselang: $( '#language' ).val() } );
		};

		di.forms.submit();
	}

	/**
	 *  On document ready we create a script tag and wire it up to run setup as soon as it
	 *  is loaded, or to show an error message if the external script can't be loaded.
	 *  The script should already be mostly or completely preloaded at this point, thanks
	 *  to a <link rel=preload> we add in GravyGateway::execute.
	 *  Don't try to load the script if the configured src is empty (as happens on the
	 *  resultSwitcher where we may show the monthly convert modal).
	 */
	$( function () {
		switch ( $( '#payment_method' ).val() ) {
			case 'cc':
				mw.donationInterface.forms.loadScript( configFromServer.secureFieldsJsScript, setupCardForm );
				break;
			case 'google':
				mw.donationInterface.forms.loadScript( configFromServer.googleScript, setupGooglePayForm );
				break;
			case 'apple':
				mw.donationInterface.forms.loadScript( configFromServer.appleScript, setupApplePayForm );
				break;
			case 'paypal':
				if ( redirectPaypal ) {
					submitPaypal();
				}
		}
	} );
} )( jQuery, mediaWiki );
