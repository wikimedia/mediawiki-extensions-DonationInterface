/* global AdyenCheckout, Promise */
( function ( $, mw ) {
	const { getBestApplePayContactName } = require( 'ext.donationInterface.applePayHelper' );
	const redirectPaymentMethods = [ 'ideal' ];
	// Adyen Web's component classes. window.AdyenWeb only exists once the Adyen
	// script has loaded, so these are set in setAdyenComponents() from setup()
	let CARD_COMPONENT,
		ACH_COMPONENT,
		SEPA_DIRECT_DEBIT_COMPONENT,
		REDIRECT_COMPONENT,
		ONLINE_BANKING_CZ_COMPONENT,
		APPLEPAY_COMPONENT,
		GOOGLEPAY_COMPONENT,
		BLIK_COMPONENT,
		VIPPS_COMPONENT;
	// promise objects are for Apple Pay - see comments below
	let checkout, onSubmit, authPromise, submitPromise,
		configFromServer = mw.config.get( 'adyenConfiguration' ),
		payment_method = $( '#payment_method' ).val(),
		onBrandSubmethod = '',
		country = $( '#country' ).val(),
		language = $( '#language' ).val(),
		ACH_GET_DONOR_ADDRESS = true; // set false to remove address from form

	/**
	 * Get extra configuration values for specific payment types
	 *
	 * @param {string} type Adyen-side name of component type
	 * @param component
	 * @param {Object} checkoutConfig The config object used to instantiate the Adyen Checkout object
	 * @return Object
	 */
	function getComponentConfig( component, checkoutConfig ) {
		const config = {};
		switch ( component ) {
			case CARD_COMPONENT:
				// Note: Debug messages are only sent and logged server-side if
				// $wgDonationInterfaceLogDebug (or $wgAdyenCheckoutGatewayLogDebug) is true

				config.onBrand = function ( brandInfo ) {
					const message = brandInfo.brand ?
						'onBrand returned brand: ' + brandInfo.brand :
						'onBrand returned: ' + JSON.stringify( brandInfo );
					if ( brandInfo.brand ) {
						onBrandSubmethod = brandInfo.brand;
					}
					mw.donationInterface.forms.addDebugMessage( message );
				};

				config.onBinLookup = function ( binLookupInfo ) {
					const message = binLookupInfo.detectedBrands && binLookupInfo.detectedBrands.length > 0 ?
						'onBinLookup returned detected brands: ' + JSON.stringify( binLookupInfo.detectedBrands ) :
						'onBinLookup returned: ' + JSON.stringify( binLookupInfo );
					mw.donationInterface.forms.addDebugMessage( message );
				};

				config.showBrandsUnderCardNumber = false;
				config.locale = checkoutConfig.locale;
				return config;

			case ACH_COMPONENT:
				config.billingAddressRequired = ACH_GET_DONOR_ADDRESS;
				return config;
			case REDIRECT_COMPONENT:
			case ONLINE_BANKING_CZ_COMPONENT:
			case SEPA_DIRECT_DEBIT_COMPONENT:
			case VIPPS_COMPONENT:
			case BLIK_COMPONENT:
				if ( redirectPaymentMethods.includes( payment_method ) ) {
					config.type = payment_method;
				}
				// for ach, ideal, CZ bank transfers, vipps, and sepa additional config is optional
				return config;

			case APPLEPAY_COMPONENT:
				// for applepay, additional config is required
				var amount = {},
					currency = $( '#currency' ).val(),
					amount_value = $( '#amount' ).val();
				amount.currency = currency;
				amount.value = amountInMinorUnits( amount_value, currency );
				config.amount = amount;
				config.countryCode = country;
				config.buttonLocale = checkoutConfig.locale;
				config.requiredBillingContactFields = [
					'name',
					'postalAddress'
				];
				config.requiredShippingContactFields = [
					'name',
					'email'
				];

				authPromise = new Promise( ( authResolve, authReject ) => {
					config.onAuthorized = function ( data, actions ) {
						const event = data.authorizedEvent;
						let bContact = event.payment.billingContact,
							sContact = event.payment.shippingContact,
							extraData = {};
						extraData = getBestApplePayContactName( extraData, bContact, sContact );
						extraData.postal_code = bContact.postalCode;
						extraData.state_province = bContact.administrativeArea;
						extraData.city = bContact.locality;
						if ( bContact.addressLines.length > 0 ) {
							extraData.street_address = bContact.addressLines[ 0 ];
						}
						extraData.email = sContact.emailAddress;
						extraData.payment_submethod = mapAppleNetworkToSubmethod( event.payment.token.paymentMethod.network );
						// Let Adyen go on to onSubmit. The payment sheet stays open until
						// onSubmit's actions are settled, after our donate API call.
						actions.resolve();
						// We will combine this contact data with a token from the
						// onSubmit event after both events have fired.
						authResolve( extraData );
					};
				} );
				// For Apple Pay show the branded button with 'Donate with 🍎Pay'
				// text as opposed to our standard blue Donate button
				config.showPayButton = true;
				config.buttonType = 'donate';
				// When the donor clicks the donate button, this event is fired with
				// a validationUrl provided by Apple. We have to make a server-side
				// request to get a big blob of Apple Pay session data, then send it
				// via the resolve function back to the component, which apparently
				// sends it back to the native widget via completeMerchantValidation.
				// https://developer.apple.com/documentation/apple_pay_on_the_web/apple_pay_js_api/providing_merchant_validation
				config.onValidateMerchant = function ( resolve, reject, validationUrl ) {
					const api = new mw.Api();
					api.post( {
						action: 'di_applesession_adyen',
						validation_url: validationUrl,
						wmf_token: $( '#wmf_token' ).val()
					} ).then( ( data ) => {
						if ( data.result && data.result.errors ) {
							mw.donationInterface.validation.showErrors( data.result.errors );
							reject();
						} else {
							resolve( data.session );
						}
					} );
				};
				return config;

			case GOOGLEPAY_COMPONENT:
				// for googlepay, additional config is required
				var g_amount = {},
					g_currency = $( '#currency' ).val(),
					g_amount_value = $( '#amount' ).val(),
					languagesSupportedByGPayButton = [
						'ar', 'bg', 'ca', 'cs', 'da', 'de', 'en', 'el', 'es', 'et', 'fi', 'fr', 'hr', 'id', 'it', 'ja',
						'ko', 'ms', 'nl', 'no', 'pl', 'pt', 'ru', 'sk', 'sl', 'sr', 'sv', 'th', 'tr', 'uk', 'zh'
					], baseLanguageCode = checkoutConfig.locale.slice( 0, 2 );
				g_amount.currency = g_currency;
				g_amount.value = amountInMinorUnits( g_amount_value, g_currency );
				config.amount = g_amount;
				config.countryCode = country;
				// Adyen maps only lowercase 'test' to Google's TEST environment; anything
				// else, including 'TEST', becomes PRODUCTION
				config.environment = configFromServer.environment;
				config.showPayButton = true;
				// When we are showing the form in a language for which Google Pay has no
				// translations of their button text, use the plain 'GPay' button rather
				// than the 'Donate with GPay' button.
				if ( !languagesSupportedByGPayButton.includes( baseLanguageCode ) ) {
					config.buttonType = 'plain';
				} else {
					config.buttonType = 'donate';
				}
				config.emailRequired = true;
				config.billingAddressRequired = true;
				config.allowedCardNetworks = configFromServer.googleAllowedNetworks;
				config.billingAddressParameters = {
					format: 'FULL'
				};
				// Adyen Web v6 reads these from configuration, and throws when merchantId is missing
				config.configuration = {
					// called gatewayMerchantId but actually our account name with Adyen
					gatewayMerchantId: configFromServer.merchantAccountName,
					merchantId: configFromServer.googleMerchantId
				};

				authPromise = new Promise( ( authResolve ) => {
					config.onAuthorized = function ( data, actions ) {
						// Google Pay's PaymentData
						const response = data.authorizedEvent,
							bContact = response.paymentMethodData.info.billingAddress,
							extraData = {};
						extraData.postal_code = bContact.postalCode;
						extraData.state_province = bContact.administrativeArea;
						extraData.city = bContact.locality;
						extraData.street_address = bContact.address1;
						extraData.email = response.email;
						extraData.full_name = bContact.name;
						extraData.payment_submethod = mapAdyenSubmethod(
							response.paymentMethodData.info.cardNetwork.toLowerCase()
						);
						// Let Adyen go on to onSubmit. The payment sheet stays open until
						// onSubmit's actions are settled, after our donate API call.
						actions.resolve();
						// We will combine this contact data with a token from the
						// onSubmit event after both events have fired.
						authResolve( extraData );
					};
				} );
				return config;
			default:
				throw new Error( 'Component type not found' );
		}
	}

	/**
	 * Given an amount in major currency units, e.g. dollars, returns the
	 * amount in minor units for the currency, e.g. cents. For non-fractional
	 * currencies just rounds the amount to the nearest whole number.
	 *
	 * @param {number} amount
	 * @param {string} currency
	 * @return {number} amount in minor units for specified currency
	 */
	function amountInMinorUnits( amount, currency ) {
		const threeDecimals = mw.config.get( 'DonationInterfaceThreeDecimalCurrencies' ),
			noDecimals = mw.config.get( 'DonationInterfaceNoDecimalCurrencies' );

		if ( noDecimals.includes( currency ) ) {
			return Math.round( amount );
		}
		if ( threeDecimals.includes( currency ) ) {
			return Math.round( amount * 1000 );
		}
		return Math.round( amount * 100 );
	}

	/**
	 * Set up Adyen Checkout object
	 *
	 * @param {Object} config requires clientKey, environment, locale,
	 *  and paymentMethodsResponse
	 * @return {AdyenCheckout}
	 */
	function getCheckout( config ) {
		config.onSubmit = onSubmit;
		config.onAdditionalDetails = onAdditionalDetails;
		config.onError = onError;
		config.showPayButton = false;
		const { AdyenCheckout } = window.AdyenWeb;

		return new Promise( ( resolve, reject ) => {
			resolve( AdyenCheckout );
		} );
	}

	function mapAppleNetworkToSubmethod( network ) {
		network = network.toLowerCase();
		switch ( network ) {
			case 'amex':
			case 'discover':
			case 'jcb':
			case 'visa':
				return network;
			case 'cartesbancaires':
				return 'cb';
			case 'electron':
				return 'visa-electron';
			case 'mastercard':
				return 'mc';
			default:
				return '';
		}
	}

	function getEWalletComponents( submethod ) {
		switch ( submethod ) {
			case 'vipps':
				return VIPPS_COMPONENT;
			case 'blik':
				return BLIK_COMPONENT;
			default:
				throw new Error( 'paymentSubMethod not found' );
		}
	}
	/**
	 * Get the name of the Adyen Checkout component to instantiate
	 *
	 * @param {string} paymentMethod our top-level payment method code
	 * @return {function} Constructor of Adyen Checkout component to instantiate
	 */
	function mapPaymentMethodToComponent( paymentMethod ) {
		switch ( paymentMethod ) {
			case 'dd':
				return ACH_COMPONENT;
			case 'cc':
				return CARD_COMPONENT;
			case 'rtbt':
				if ( mw.config.get( 'payment_submethod' ) === 'sepadirectdebit' ) {
				return SEPA_DIRECT_DEBIT_COMPONENT;
				} else {
					return REDIRECT_COMPONENT;
				}
			case 'bt':
				return ONLINE_BANKING_CZ_COMPONENT;
			case 'apple':
				return APPLEPAY_COMPONENT;
			case 'google':
				return GOOGLEPAY_COMPONENT;
			case 'ew':
				return getEWalletComponents(  mw.config.get( 'payment_submethod' ) );
			default:
				throw new Error( 'paymentMethod not found' );
		}
	}

	/**
	 * TODO: should we do this mapping server-side
	 * using SmashPig's ReferenceData?
	 *
	 * @param {string} adyenBrandCode Adyen-side identifier for the payment submethod
	 * @return {string} Our identifier for the payment submethod
	 */
	function mapAdyenSubmethod( adyenBrandCode ) {
		switch ( adyenBrandCode ) {
			case 'bijcard':
				return 'bij';
			case 'cartebancaire':
				return 'cb';
			case 'maestro_usa':
				return 'maestro';
			case 'mc-debit':
				return 'mc';
			case 'visadankort':
				return 'visa';
			case 'visadebit':
			case 'vpay':
				return 'visa-debit';
			case 'visabeneficial':
				return 'visa-beneficial';
			case 'visaelectron':
				return 'visa-electron';
			case 'mastercard':
				return 'mc';
			default:
				return adyenBrandCode;
		}
	}

	submitPromise = new Promise( ( submitResolve, submitReject ) => {
		onSubmit = function ( state, component, actions ) {
			let extraData = {};
			// Submit to our server, unless it's Apple or Google Pay, which submit once
			// both their onAuthorized and onSubmit events have fired.
			if ( mw.donationInterface.validation.validate() && state.isValid ) {
				switch ( payment_method ) {
					case 'dd':
						extraData = {
							encrypted_bank_account_number: state.data.paymentMethod.encryptedBankAccountNumber,
							encrypted_bank_location_id: state.data.paymentMethod.encryptedBankLocationId,
							full_name: state.data.paymentMethod.ownerName,
							bank_account_type: $( '#bank_account_type' ).val()
						};
						if ( ACH_GET_DONOR_ADDRESS ) {
							extraData.supplemental_address_1 = state.data.billingAddress.houseNumberOrName !== 'N/A' ? state.data.billingAddress.houseNumberOrName : '';
							extraData.country = state.data.billingAddress.country;
							extraData.street_address = state.data.billingAddress.street;
							extraData.postal_code = state.data.billingAddress.postalCode;
							extraData.city = state.data.billingAddress.city;
							extraData.state_province = state.data.billingAddress.stateOrProvince;
						}
						break;
					case 'rtbt':
						switch ( state.data.paymentMethod.type ) {
							case 'ideal':
								extraData = {
									// issuer is bank chosen from dropdown
									issuer_id: state.data.paymentMethod.issuer,
									payment_submethod: 'rtbt_ideal'
								};
								break;
							case 'sepadirectdebit':
								extraData = {
									full_name: state.data.paymentMethod.ownerName,
									// The International Bank Account Number
									iban: state.data.paymentMethod.iban,
									payment_submethod: 'sepadirectdebit'
								};
								break;
						}
						break;
					case 'bt':
						extraData = {
							// issuer is bank chosen from dropdown
							issuer_id: state.data.paymentMethod.issuer
						};
						break;
					case 'cc':
						extraData = {
							encrypted_card_number: state.data.paymentMethod.encryptedCardNumber,
							encrypted_expiry_month: state.data.paymentMethod.encryptedExpiryMonth,
							encrypted_expiry_year: state.data.paymentMethod.encryptedExpiryYear,
							encrypted_security_code: state.data.paymentMethod.encryptedSecurityCode,
							// The code should be available in state.data.paymentMethod.brand, but
							// sometimes it's not there. We can usually still find it via component.
							payment_submethod: mapAdyenSubmethod(
								state.data.paymentMethod.brand || component.state.brand || onBrandSubmethod
							)
						};
						if ( state.data.browserInfo ) {
							extraData.color_depth = state.data.browserInfo.colorDepth;
							extraData.java_enabled = state.data.browserInfo.javaEnabled;
							extraData.screen_height = state.data.browserInfo.screenHeight;
							extraData.screen_width = state.data.browserInfo.screenWidth;

							// Ensure timeZoneOffset is always an integer, rounding down if it's a decimal or float.
							// See T380741
							if ( typeof state.data.browserInfo.timeZoneOffset !== 'undefined' &&
								!isNaN( Math.floor( state.data.browserInfo.timeZoneOffset ) ) ) {
								extraData.time_zone_offset = Math.floor( state.data.browserInfo.timeZoneOffset );
							} else {
								extraData.time_zone_offset = state.data.browserInfo.timeZoneOffset;
							}
						}
						break;
					case 'google':
						// Resolve the submit promise with the Google Pay token and bail out - we
						// also need to wait for the onAuthorized event with contact data.
						// Pass on the actions too: settling them closes the payment sheet.
						submitResolve( { payment_token: state.data.paymentMethod.googlePayToken, actions } );
						return;
					case 'apple':
						// Resolve the submit promise with the Apple Pay token and bail out - we
						// also need to wait for the onAuthorized event with contact data.
						// Pass on the actions too: settling them closes the payment sheet.
						submitResolve( { payment_token: state.data.paymentMethod.applePayToken, actions } );
						return;
				}

				// Allow other scripts (e.g. variants) to provide more data to submit
				if ( typeof mw.donationInterface.getExtraData === 'function' ) {
					Object.assign( extraData, mw.donationInterface.getExtraData() );
				}

				mw.donationInterface.forms.callDonateApi(
					handleApiResult, extraData, 'di_donate_adyen'
				);
			}
		};
	} );
	/**
	 * Settles Adyen's onSubmit actions for Apple or Google Pay once our donate API has answered,
	 * so the component closes the payment sheet with success or failure.
	 *
	 * Our API doesn't pass Adyen's own resultCode through, only whether the donation
	 * failed, so map that to the resultCode the component checks. Adyen counts
	 * Cancelled, Error and Refused as failed payments.
	 *
	 * @param {Object} actions onSubmit's actions, with resolve and reject
	 * @return {Object} Success and failure callbacks for callDonateApi
	 */
	function handleWalletApiResult( actions ) {
		return {
			onSuccess: ( result ) => {
				actions.resolve( { resultCode: result.isFailed ? 'Refused' : 'Authorised' } );
				handleApiResult( result );
			},
			// The API reported errors or the request failed: callDonateApi has shown the error
			onFailure: () => {
				actions.reject();
			}
		};
	}

	function handleApiResult( result ) {
		if ( result.isFailed ) {
			location.assign( mw.config.get( 'DonationInterfaceFailUrl' ) );
			return;
		}

		if ( result.formData && Object.keys( result.formData ).length > 0 ) {
			// FIXME: reconstructing the raw result from the API
			// which has been normalized down to just these two
			// fields. Should we just pass the raw Adyen API result
			// back to the front end? Seems like we would only want
			// a rawResult property on the front-end donation API
			// response when we are damn sure it's sanitized.
			checkout.createFromAction( {
				paymentMethodType: 'scheme',
				url: result.redirect,
				data: result.formData,
				method: 'POST',
				type: 'redirect'
			} ).mount( '#action-container' );

		// canShowModal() is just a sanity check to see if the required DOM elements
		// are there.
		} else if ( mw.monthlyConvert && mw.monthlyConvert.canShowModal() ) {
			mw.monthlyConvert.init();
		} else if ( result.redirect ) {
			document.location.replace( result.redirect );
		} else {
			document.location.replace( mw.config.get( 'DonationInterfaceThankYouPage' ) );
		}
	}

	function onAdditionalDetails( state, dropin ) {
		// Handle 3D secure
	}

	// T292571 try catch the adyen error, see if any connection been blocked, e.g. iframe
	function onError( error ) {
		// Ignore blank string - that means a previous error was cleared up
		if ( typeof error.error === 'string' && error.error === '' ) {
			return;
		}
		// Send error on donor-initiated cancellations
		if ( error && error.cause && error.cause.statusCode === 'CANCELED' ) {
			location.assign( mw.config.get( 'DonationInterfaceCancelUrl' ) );
			return;
		}
		// The donor closed the Apple Pay sheet. Adyen reports that as a CANCEL error
		if ( error && error.name === 'CANCEL' ) {
			return;
		}
		if ( typeof error.error === 'string' && error.error.slice( 0, 8 ) === 'error.va' ) {
			// T349600 Log validation error codes only if sf-cc-num.02 donate_interface-error-msg-card-number-do-not-match-card-brand)
			// with date time and time zone for adyen to investigate their card validation js issue
			if ( error.error === 'error.va.sf-cc-num.02' ) {
				error.error = 'Adyen error: ' + error.error + ' on ' + new Date().toString();
			} else {
				return;
			}
		} else {
			// handle component error
			mw.donationInterface.validation.showErrors( {
				general: mw.msg( 'donate_interface-error-msg-general' )
			} );
		}
		throw error;
	}

	function setLocaleAndTranslations( config, localeFromServer ) {
		// Adyen supports the locales listed below, according to
		// https://docs.adyen.com/online-payments/classic-integrations/checkout-sdks/web-sdk/customization/localization/
		const adyenSupportedLocale = [
			'zh-CN', 'zh-TW', 'hr-HR', 'cs-CZ',
			'da-DK', 'nl-NL', 'en-US', 'fi-FI',
			'fr-FR', 'de-DE', 'el-GR', 'hu-HU',
			'it-IT', 'ja-JP', 'ko-KR', 'no-NO',
			'pl-PL', 'pt-BR', 'ro-RO', 'ru-RU',
			'sk-SK', 'sl-SL', 'es-ES', 'sv-SE'
		], baseLocaleFromServer = localeFromServer.slice( 0, 2 );

		// We support Norwegian Bokmal (nb) but Adyen's components just support the generic 'no' Norwegian code
		if ( baseLocaleFromServer === 'nb' ) {
			config.locale = 'no-NO';
		} else {
			config.locale = localeFromServer;
		}

		config.translations = {};
		// Check if donor's language is unsupported by Adyen and we need to provide our own customized translation
		// Adyen supports ar as Arabic - International and doesn't check the country part
		if ( baseLocaleFromServer !== 'ar' && !adyenSupportedLocale.includes( config.locale ) ) {
			config.translations[ config.locale ] = {
				//title
				'creditCard.cardNumber.label': mw.msg( 'donate_interface-credit-card-number' ),
				'creditCard.expiryDate.label': mw.msg( 'donate_interface-credit-card-expiration' ),
				'creditCard.securityCode.label': mw.msg( 'donate_interface-cvv' ),
				//Adyen no longer uses placeholders in v6, they use contextualText instead
				'creditCard.expiryDate.contextualText': mw.msg( 'donate_interface-expiry-date-field-placeholder' ),
				'creditCard.securityCode.contextualText.3digits': mw.msg( 'donate_interface-cvv-placeholder-3-digits' ),
				'creditCard.securityCode.contextualText.4digits': mw.msg( 'donate_interface-cvv-placeholder-4-digits' ),
				//error
				'creditCard.numberField.invalid': mw.msg( 'donate_interface-error-msg-invalid-card-number' ),
				'creditCard.expiryDateField.invalid': mw.msg( 'donate_interface-error-msg-expiry-date-field-invalid' ),
				'error.va.gen.01': mw.msg( 'donate_interface-error-msg-incomplete-field' ),
				'error.va.gen.02': mw.msg( 'donate_interface-error-msg-field-not-valid' ),
				'error.va.sf-cc-num.01': mw.msg( 'donate_interface-error-msg-invalid-card-number' ),
				'error.va.sf-cc-num.02': mw.msg( 'donate_interface-error-msg-card-number-do-not-match-card-brand' ),
				'error.va.sf-cc-num.03': mw.msg( 'donate_interface-error-msg-unsupported-card-entered' ),
				'error.va.sf-cc-dat.01': mw.msg( 'donate_interface-error-msg-card-too-old' ),
				'error.va.sf-cc-dat.02': mw.msg( 'donate_interface-error-msg-date-too-far-in-the-future' ),
				// Adyen v6 error codes
				// https://github.com/Adyen/adyen-web/blob/a05c1184195fa73ce7dc9e956fa080cb40638209/packages/server/translations/en-US.json
				'cc.num.900': mw.msg( 'donate_interface-error-msg-card-num' ),
				'cc.num.901': mw.msg( 'donate_interface-error-msg-invalid-card-number' ),
				'cc.num.902': mw.msg( 'donate_interface-error-msg-invalid-card-number' ),
				'cc.num.903': mw.msg( 'donate_interface-error-msg-unsupported-card-entered' ),
				'cc.num.904': mw.msg( 'donate_interface-error-msg-unsupported-card-entered' ),
				'cc.dat.910': mw.msg( 'donate_interface-error-msg-expiration' ),
				'cc.dat.911': mw.msg( 'donate_interface-error-msg-expiry-date-field-invalid' ),
				'cc.dat.912': mw.msg( 'donate_interface-error-msg-expiry-date-field-invalid' ),
				'cc.dat.913': mw.msg( 'donate_interface-error-msg-expiry-date-field-invalid' ),
				'cc.cvc.920': mw.msg( 'donate_interface-error-msg-invalid-cvv-format' ),
				'cc.cvc.921': mw.msg( 'donate_interface-error-msg-invalid-cvv-format' ),
				'cc.cvc.920.amex': mw.msg( 'donate_interface-error-msg-invalid-cvv-format' ),
				'cc.cvc.921.amex': mw.msg( 'donate_interface-error-msg-invalid-cvv-format' ),
				'err.gen.9100': mw.msg( 'donate_interface-error-msg-incomplete-field' ),
				'err.gen.9101': mw.msg( 'donate_interface-error-msg-field-not-valid' )
			};
		} else if ( config.locale === 'nl-NL' ) {
			config.translations[ config.locale ] = {
				'idealIssuer.selectField.placeholder': mw.msg( 'donate_interface-rtbt-issuer_id' )
			};
		} else if ( language === 'ja' ) {
			config.translations[ config.locale ] = {
				'creditCard.expiryDateField.placeholder': mw.msg( 'donate_interface-expiry-date-field-placeholder' ),
				'creditCard.cvcField.placeholder.3digits': '',
				'creditCard.cvcField.placeholder.4digits': ''
			};
		} else {
			config.translations[ config.locale ] = {};
		}
		if ( mw.config.get( 'payment_submethod' ) === 'ach' ) {
			config.translations[ config.locale ] = {
				'error.va.sf-ach-loc.02': mw.msg( 'donate_interface-ach-invalid-routing-number-count' ),
				'error.va.sf-ach-num.02': mw.msg( 'donate_interface-ach-invalid-account-number-count' )
			};
		}

		// if sepa, update holder name to account holder name
		if ( mw.config.get( 'payment_submethod' ) === 'sepadirectdebit' ) {
			config.translations[ config.locale ][ 'sepa.ownerName' ] = mw.msg( 'donate_interface-bt-account_holder' );
		}
		// Allow other scripts (e.g. variants) to provide more translations to the Adyen components
		if ( mw.donationInterface.extraTranslations ) {
			Object.assign( config.translations[ config.locale ], mw.donationInterface.extraTranslations );
		}
	}

	/**
	 * Picks out the Adyen Web component classes, once the Adyen script has loaded
	 */
	function setAdyenComponents() {
		CARD_COMPONENT = window.AdyenWeb.Card;
		ACH_COMPONENT = window.AdyenWeb.Ach;
		SEPA_DIRECT_DEBIT_COMPONENT = window.AdyenWeb.SepaDirectDebit;
		ONLINE_BANKING_CZ_COMPONENT = window.AdyenWeb.OnlineBankingCZ;
		APPLEPAY_COMPONENT = window.AdyenWeb.ApplePay;
		GOOGLEPAY_COMPONENT = window.AdyenWeb.GooglePay;
		VIPPS_COMPONENT = window.AdyenWeb.Vipps;
		BLIK_COMPONENT = window.AdyenWeb.Blik;
		// iDEAL and other bank redirects
		REDIRECT_COMPONENT = window.AdyenWeb.Redirect;
	}

	/**
	 * Runs as soon as the external Adyen checkout script is loaded
	 */
	function setup() {
		let component,
			config,
			containerName = 'component-container',
			oldShowErrors,
			checkoutPromise;

		setAdyenComponents();
		component = mapPaymentMethodToComponent( payment_method );

		// Drop in the adyen components placeholder container
		$( '.submethods' ).before(
			'<div id="' + containerName + '" />'
		).before(
			'<div id="action-container" />'
		);

		// add name placeholder for ja JP
		if ( language === 'ja' ) {
			$( '#last_name' ).attr( 'placeholder', '鈴木' );
			$( '#first_name' ).attr( 'placeholder', '太郎' );
		}

		// Override validation's showErrors function to add error
		// highlights to the outer div around the secure field iframe.
		// FIXME: cleaner object-oriented JS with inheritance would
		// make this prettier. See https://phabricator.wikimedia.org/T293287
		oldShowErrors = mw.donationInterface.validation.showErrors;
		mw.donationInterface.validation.showErrors = function ( errors ) {
			let adyenFieldName;
			$.each( errors, ( field ) => {
				adyenFieldName = false;
				if ( field === 'card_num' || field === 'encrypted_card_number' ) {
					adyenFieldName = 'encryptedCardNumber';
				} else if ( field === 'encrypted_expiry_month' || field === 'encrypted_expiry_year' ) {
					adyenFieldName = 'encryptedExpiryDate';
				} else if ( field === 'cvv' ) {
					adyenFieldName = 'encryptedSecurityCode';
				}
				if ( adyenFieldName ) {
					$( 'span[data-cse=' + adyenFieldName + ']' )
						.closest( '.adyen-checkout__input-wrapper' )
						.addClass( 'errorHighlight' );
				}
			} );
			oldShowErrors( errors );
		};

		// Copy values to leave the mw.config setting untouched
		config = {
			clientKey: configFromServer.clientKey,
			environment: configFromServer.environment,
			paymentMethodsResponse: configFromServer.paymentMethodsResponse
		};

		setLocaleAndTranslations( config, configFromServer.locale );

		checkoutPromise = getCheckout( config );
		checkoutPromise.then( async ( checkoutObject ) => {
			config.countryCode = country;
			checkout = await checkoutObject( config );
			createAndMountComponent( config, containerName );
		} );
	}

	const getComponentForPaymentMethod = ( config ) => {
		const PaymentMethodComponent = mapPaymentMethodToComponent( payment_method );
		const component_config = getComponentConfig( PaymentMethodComponent, config );
		return new PaymentMethodComponent( checkout, component_config );
	};

	function createAndMountComponent( config, containerName ) {
		const component = getComponentForPaymentMethod( config );

		if ( component instanceof GOOGLEPAY_COMPONENT ) {
			component.isAvailable().then( () => {
				component.mount( '#' + containerName );
			} ).catch( () => {
				mw.donationInterface.validation.showErrors( {
					general: mw.message(
						'donate_interface-error-msg-google_pay_unsupported',
						mw.config.get( 'DonationInterfaceOtherWaysURL' )
					).plain()
				} );
			} );
			// For Google Pay, we need contact data from the onAuthorized event and token
			// data from the onSubmit event before we can make our MediaWiki API call.
			Promise.all( [ submitPromise, authPromise ] ).then( ( [ submitArgs, authArgs ] ) => {
				const extraData = authArgs,
					googlePayResult = handleWalletApiResult( submitArgs.actions );
				extraData.payment_token = submitArgs.payment_token;
				mw.donationInterface.forms.callDonateApi(
					googlePayResult.onSuccess, extraData, 'di_donate_adyen', googlePayResult.onFailure
				);
			} ).catch( ( err ) => {
				mw.donationInterface.validation.showErrors( {
					general: mw.msg( 'donate_interface-error-msg-general' )
				} );
				// Let error bubble up to window.onerror handler so the errorLog
				// module sends it to our client-side logging endpoint.
				throw err;
			} );
		} else if ( component instanceof APPLEPAY_COMPONENT ) {
			component.isAvailable().then( () => {
				component.mount( '#' + containerName );
			} ).catch( () => {
				mw.donationInterface.validation.showErrors( {
					general: mw.message(
						'donate_interface-error-msg-apple_pay_unsupported',
						mw.config.get( 'DonationInterfaceOtherWaysURL' )
					).plain()
				} );
			} );
			// For Apple Pay, we need contact data from the onAuthorized event and token
			// data from the onSubmit event before we can make our MediaWiki API call.
			Promise.all( [ submitPromise, authPromise ] ).then( ( [ submitArgs, authArgs ] ) => {
				const extraData = authArgs,
					applePayResult = handleWalletApiResult( submitArgs.actions );
				extraData.payment_token = submitArgs.payment_token;
				mw.donationInterface.forms.callDonateApi(
					applePayResult.onSuccess, extraData, 'di_donate_adyen', applePayResult.onFailure
				);
			} ).catch( ( err ) => {
				mw.donationInterface.validation.showErrors( {
					general: mw.msg( 'donate_interface-error-msg-general' )
				} );
				// Let error bubble up to window.onerror handler so the errorLog
				// module sends it to our client-side logging endpoint.
				throw err;
			} );
		} else {
			try {
				component.mount( '#' + containerName );
			} catch ( err ) {
				mw.donationInterface.validation.showErrors( {
					general: mw.msg( 'donate_interface-error-msg-general' )
				} );
				throw err;
			}
			// For everything except Apple and google
			// Pay, show our standard 'Donate' button
			$( '#paymentSubmit' ).show();
			$( '#paymentSubmitBtn' ).click( mw.util.debounce( ( evt ) => {
				component.submit( evt );
			}, 100 ) );
		}
	}

	$( () => {
		if ( !configFromServer ) {
			// If the configuration has not been passed from the server, we are likely on the
			// ResultSwitcher page and have just been loaded incidentally to make a form for
			// a backdrop of the monthly convert popup. As the rest of this function is only
			// needed to set up payment widgets which will not be needed here, just quit.
			// It might be better to stop loading adyen.js in that situation, but that's a
			// bigger refactor than we want to do right now.
			return;
		}
		if ( payment_method === 'google' ) {
			// loadScript will display an error message when the script fails to load
			// so we just suppress the rejection with an empty catch block here.
			mw.donationInterface.forms.loadScript( configFromServer.googleScript )
				.catch( () => {} );
		}
		mw.donationInterface.forms.loadScript( configFromServer.script.src, true, configFromServer.script.integrity )
			.then( setup )
			.catch( () => {} );
	} );
} )( jQuery, mediaWiki );
