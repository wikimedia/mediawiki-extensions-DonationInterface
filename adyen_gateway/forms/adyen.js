/* global AdyenCheckout, Promise */
( function ( $, mw ) {
	// promise objects are for Apple Pay - see comments below
	var checkout, onSubmit, authPromise, submitPromise,
		configFromServer = mw.config.get( 'adyenConfiguration' ),
		payment_method = $( '#payment_method' ).val(),
		country = $( '#country' ).val(),
		language = $( '#language' ).val(),
		// This is the old-style Google Pay integration type currently active on
		// our account. Older versions of the Adyen JS SDK treated the 'googlepay'
		// component type as the old GPay integration, but for newer versions of
		// the GPay SDK we need to explicitly specify 'paywithgoogle' to get tokens
		// that work with the old-style integration. At some point we should upgrade
		// to the new interaction, but that will require coordinating an update to
		// this constant with an update to our account.
		GOOGLEPAY_COMPONENT_TYPE = 'paywithgoogle';

	/**
	 * Get extra configuration values for specific payment types
	 *
	 * @param {string} type Adyen-side name of component type
	 * @param {Object} checkoutConfig The config object used to instantiate the Adyen Checkout object
	 * @return Object
	 */
	function getComponentConfig( type, checkoutConfig ) {
		var config = {};
		switch ( type ) {
			case 'card':
				// Note: Debug messages are only sent and logged server-side if
				// $wgDonationInterfaceLogDebug (or $wgAdyenCheckoutGatewayLogDebug) is true

				config.onBrand = function ( brandInfo ) {
					var message = brandInfo.brand ?
						'onBrand returned brand: ' + brandInfo.brand :
						'onBrand returned: ' + JSON.stringify( brandInfo );
					mw.donationInterface.forms.addDebugMessage( message );
				};

				config.onBinLookup = function ( binLookupInfo ) {
					var message = binLookupInfo.detectedBrands && binLookupInfo.detectedBrands.length > 0 ?
						'onBinLookup returned detected brands: ' + JSON.stringify( binLookupInfo.detectedBrands ) :
						'onBinLookup returned: ' + JSON.stringify( binLookupInfo );
					mw.donationInterface.forms.addDebugMessage( message );
				};

				config.showBrandsUnderCardNumber = false;
				return config;

			case 'ach':
			case 'ideal':
			case 'onlineBanking_CZ':
			case 'sepadirectdebit':
				// for ach, ideal, CZ bank transfers, and sepa additional config is optional
				return config;

			case 'applepay':
				// for applepay, additional config is required
				var amount = {},
					currency = $( '#currency' ).val(),
					amount_value = $( '#amount' ).val();
				amount.currency = currency;
				amount.value = amountInMinorUnits( amount_value, currency );
				config.amount = amount;
				config.countryCode = country;
				config.requiredBillingContactFields = [
					'name',
					'postalAddress'
				];
				config.requiredShippingContactFields = [
					'name',
					'email'
				];

				authPromise = new Promise( function ( authResolve, authReject ) {
					config.onAuthorized = function ( resolve, reject, event ) {
						var bContact = event.payment.billingContact,
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
						// We will combine this contact data with a token from the
						// onSubmit event after both events have fired.
						authResolve( extraData );

						resolve();
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
					var api = new mw.Api();
					api.post( {
						action: 'di_applesession_adyen',
						validation_url: validationUrl,
						wmf_token: $( '#wmf_token' ).val()
					} ).then( function ( data ) {
						if ( data.result && data.result.errors ) {
							mw.donationInterface.validation.showErrors( data.result.errors );
							reject();
						} else {
							resolve( data.session );
						}
					} );
				};

				return config;

			case GOOGLEPAY_COMPONENT_TYPE:
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
				config.environment = configFromServer.environment.toUpperCase();
				config.showPayButton = true;
				// When we are showing the form in a language for which Google Pay has no
				// translations of their button text, use the plain 'GPay' button rather
				// than the 'Donate with GPay' button.
				if ( languagesSupportedByGPayButton.indexOf( baseLanguageCode ) === -1 ) {
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
				// called gatewayMerchantId but actually our account name with Adyen
				config.gatewayMerchantId = configFromServer.merchantAccountName;
				config.merchantId = configFromServer.googleMerchantId;

				authPromise = new Promise( function ( authResolve ) {
					config.onAuthorized = function ( response ) {
						var bContact = response.paymentMethodData.info.billingAddress,
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
		var threeDecimals = mw.config.get( 'DonationInterfaceThreeDecimalCurrencies' ),
			noDecimals = mw.config.get( 'DonationInterfaceNoDecimalCurrencies' );

		if ( noDecimals.indexOf( currency ) !== -1 ) {
			return Math.round( amount );
		}
		if ( threeDecimals.indexOf( currency ) !== -1 ) {
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
		var checkoutObject = new AdyenCheckout( config );
		if ( checkoutObject instanceof Promise ) {
			return checkoutObject;
		}

		return new Promise( function ( resolve, reject ) {
			resolve( checkoutObject );
		} );
	}

	/**
	 * Try to obtain the "best" name from the available contact info sent back by Apple pay
	 *
	 * @see https://developer.apple.com/documentation/apple_pay_on_the_web/applepaypaymentrequest/2216120-requiredbillingcontactfields
	 * @see https://developer.apple.com/documentation/apple_pay_on_the_web/applepaypaymentcontact
	 * @param extraData
	 * @param billingContact
	 * @param shippingContact
	 * @return {*}
	 */
	function getBestApplePayContactName( extraData, billingContact, shippingContact ) {
		var first_name, last_name;

		if ( billingContact && billingContact.givenName && billingContact.givenName.length > 1 ) {
			first_name = billingContact.givenName;
			if ( billingContact.familyName && billingContact.familyName.length > 1 ) {
				last_name = billingContact.familyName;
			}
		}

		if ( first_name && !last_name ) {
			// suspected 'dad' scenario so use shipping contact
			if ( shippingContact && shippingContact.givenName && shippingContact.givenName.length > 1 ) {
				first_name = shippingContact.givenName;
				if ( shippingContact.familyName && shippingContact.familyName.length > 1 ) {
					last_name = shippingContact.familyName;
				}
			}
		}

		extraData.first_name = first_name;
		extraData.last_name = last_name;
		return extraData;
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

	/**
	 * Get the name of the Adyen Checkout component to instantiate
	 *
	 * @param {string} paymentMethod our top-level payment method code
	 * @return {string} name of Adyen Checkout component to instantiate
	 */
	function mapPaymentMethodToComponentType( paymentMethod ) {
		switch ( paymentMethod ) {
			case 'ach':
				return 'ach';
			case 'cc':
				return 'card';
			case 'rtbt':
				if ( mw.config.get( 'payment_submethod' ) === 'sepadirectdebit' ) {
					return 'sepadirectdebit';
				} else {
					return 'ideal';
				}
			case 'bt':
				return 'onlineBanking_CZ';
			case 'apple':
				return 'applepay';
			case 'google':
				return GOOGLEPAY_COMPONENT_TYPE;
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

	submitPromise = new Promise( function ( submitResolve, submitReject ) {
		onSubmit = function ( state, component ) {
			var extraData = {};
			// Submit to our server, unless it's Apple Pay, which submits in
			// the onAuthorized handler.
			if ( mw.donationInterface.validation.validate() && state.isValid ) {
				switch ( payment_method ) {
					case 'ach':
						extraData = {
							encrypted_bank_account_number: state.data.paymentMethod.encryptedBankAccountNumber,
							encrypted_bank_location_id: state.data.paymentMethod.encryptedBankLocationId,
							full_name: state.data.paymentMethod.ownerName,
							bank_account_type: $( '#bank_account_type' ).val(),
							// below are billing address, optional but good to have for civi
							supplemental_address_1: state.data.billingAddress.houseNumberOrName,
							country: state.data.billingAddress.country,
							street_address: state.data.billingAddress.street,
							postal_code: state.data.billingAddress.postalCode,
							city: state.data.billingAddress.city,
							state_province: state.data.billingAddress.stateOrProvince
						};
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
								state.data.paymentMethod.brand || component.state.brand
							)
						};
						if ( state.data.browserInfo ) {
							extraData.color_depth = state.data.browserInfo.colorDepth;
							extraData.java_enabled = state.data.browserInfo.javaEnabled;
							extraData.screen_height = state.data.browserInfo.screenHeight;
							extraData.screen_width = state.data.browserInfo.screenWidth;
							extraData.time_zone_offset = state.data.browserInfo.timeZoneOffset;
						}
						break;
					case 'google':
						submitResolve( state.data.paymentMethod.googlePayToken );
						return;
					case 'apple':
						// Resolve the submit promise with the Apple Pay token and bail out - we
						// also need to wait for the onAuthorized event with contact data.
						submitResolve( state.data.paymentMethod.applePayToken );
						return;
				}

				// Allow other scripts (e.g. variants) to provide more data to submit
				if ( typeof mw.donationInterface.getExtraData === 'function' ) {
					$.extend( extraData, mw.donationInterface.getExtraData() );
				}

				mw.donationInterface.forms.callDonateApi(
					handleApiResult, extraData, 'di_donate_adyen'
				);
			}
		};
	} );

	function handleApiResult( result ) {
		if ( result.isFailed ) {
			mw.donationInterface.validation.showErrors( {
				general: mw.msg( 'donate_interface-error-msg-general' )
			} );
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
		// https://docs.adyen.com/online-payments/web-components/localization-components#supported-languages
		var adyenSupportedLocale = [
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
		if ( baseLocaleFromServer !== 'ar' && adyenSupportedLocale.indexOf( config.locale ) === -1 ) {
			config.translations[ config.locale ] = {
				//title
				'creditCard.numberField.title': mw.msg( 'donate_interface-credit-card-number' ),
				'creditCard.expiryDateField.title': mw.msg( 'donate_interface-credit-card-expiration' ),
				'creditCard.cvcField.title': mw.msg( 'donate_interface-cvv' ),
				//placeholder
				'creditCard.expiryDateField.placeholder': mw.msg( 'donate_interface-expiry-date-field-placeholder' ),
				'creditCard.cvcField.placeholder.3digits': mw.msg( 'donate_interface-cvv-placeholder-3-digits' ),
				'creditCard.cvcField.placeholder.4digits': mw.msg( 'donate_interface-cvv-placeholder-4-digits' ),
				//error
				'creditCard.numberField.invalid': mw.msg( 'donate_interface-error-msg-invalid-card-number' ),
				'creditCard.expiryDateField.invalid': mw.msg( 'donate_interface-error-msg-expiry-date-field-invalid' ),
				'error.va.gen.01': mw.msg( 'donate_interface-error-msg-incomplete-field' ),
				'error.va.gen.02': mw.msg( 'donate_interface-error-msg-field-not-valid' ),
				'error.va.sf-cc-num.01': mw.msg( 'donate_interface-error-msg-invalid-card-number' ),
				'error.va.sf-cc-num.02': mw.msg( 'donate_interface-error-msg-card-number-do-not-match-card-brand' ),
				'error.va.sf-cc-num.03': mw.msg( 'donate_interface-error-msg-unsupported-card-entered' ),
				'error.va.sf-cc-dat.01': mw.msg( 'donate_interface-error-msg-card-too-old' ),
				'error.va.sf-cc-dat.02': mw.msg( 'donate_interface-error-msg-date-too-far-in-the-future' )
			};
		} else if ( config.locale === 'nl-NL' ) {
			config.translations[ config.locale ] = {
				'idealIssuer.selectField.placeholder': mw.msg( 'donate_interface-rtbt-issuer_id' )
			};
		} else if ( language === 'ja' ) {
			config.translations[ config.locale ] = {
				'creditCard.expiryDateField.placeholder': mw.msg( 'donate_interface-expiry-date-field-placeholder' )
			};
		} else {
			config.translations[ config.locale ] = {};
		}

		// Allow other scripts (e.g. variants) to provide more translations to the Adyen components
		if ( mw.donationInterface.extraTranslations ) {
			$.extend( config.translations[ config.locale ], mw.donationInterface.extraTranslations );
		}
	}

	/**
	 * Runs as soon as the external Adyen checkout script is loaded
	 */
	function setup() {
		var component_type,
			config,
			containerName = 'component-container',
			oldShowErrors,
			checkoutPromise;

		if ( !configFromServer ) {
			// If the configuration has not been passed from the server, we are likely on the
			// ResultSwitcher page and have just been loaded incidentally to make a form for
			// a backdrop of the monthly convert popup. As the rest of this function is only
			// needed to set up payment widgets which will not be needed here, just quit.
			// It might be better to stop loading adyen.js in that situation, but that's a
			// bigger refactor than we want to do right now.
			return;
		}

		component_type = mapPaymentMethodToComponentType( payment_method );

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
			var adyenFieldName;
			$.each( errors, function ( field ) {
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
		checkoutPromise.then( function ( checkoutObject ) {
			checkout = checkoutObject;
			createAndMountComponent( config, component_type, containerName );
		} );
	}

	function createAndMountComponent( config, component_type, containerName ) {
		var component_config = getComponentConfig( component_type, config ),
			component = checkout.create( component_type, component_config );

		if ( component_type === GOOGLEPAY_COMPONENT_TYPE ) {
			component.isAvailable().then( function () {
				component.mount( '#' + containerName );
			} ).catch( function () {
				mw.donationInterface.validation.showErrors( {
					general: mw.message(
						'donate_interface-error-msg-google_pay_unsupported',
						mw.config.get( 'DonationInterfaceOtherWaysURL' )
					).plain()
				} );
			} );
			// For Google Pay, we need contact data from the onAuthorized event and token
			// data from the onSubmit event before we can make our MediaWiki API call.
			Promise.all( [ submitPromise, authPromise ] ).then( function ( values ) {
				var extraData = values[ 1 ];
				extraData.payment_token = values[ 0 ];
				mw.donationInterface.forms.callDonateApi(
					handleApiResult, extraData, 'di_donate_adyen'
				);
			} ).catch( function ( err ) {
				mw.donationInterface.validation.showErrors( {
					general: mw.msg( 'donate_interface-error-msg-general' )
				} );
				// Let error bubble up to window.onerror handler so the errorLog
				// module sends it to our client-side logging endpoint.
				throw err;
			} );
		} else if ( component_type === 'applepay' ) {
			component.isAvailable().then( function () {
				component.mount( '#' + containerName );
			} ).catch( function () {
				mw.donationInterface.validation.showErrors( {
					general: mw.message(
						'donate_interface-error-msg-apple_pay_unsupported',
						mw.config.get( 'DonationInterfaceOtherWaysURL' )
					).plain()
				} );
			} );
			// For Apple Pay, we need contact data from the onAuthorized event and token
			// data from the onSubmit event before we can make our MediaWiki API call.
			Promise.all( [ submitPromise, authPromise ] ).then( function ( values ) {
				var extraData = values[ 1 ];
				extraData.payment_token = values[ 0 ];
				mw.donationInterface.forms.callDonateApi(
					handleApiResult, extraData, 'di_donate_adyen'
				);
			} ).catch( function ( err ) {
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
			$( '#paymentSubmitBtn' ).click( mw.util.debounce( function ( evt ) {
				component.submit( evt );
			}, 100 ) );
		}
	}

	/**
	 * On documentready we create a script tag and wire it up to run setup as soon as it
	 * is loaded, or to show an error message if the external script can't be loaded.
	 * The script should already be mostly or completely preloaded at this point, thanks
	 * to a <link rel=preload> we add in AdyenCheckoutGateway::addGatewaySpecificResources
	 */

	function loadScript( type ) {
		var scriptNode = document.createElement( 'script' );
		scriptNode.onerror = function () {
			mw.donationInterface.validation.showErrors(
				{ general: 'Could not load payment provider Javascript. Please reload or try again later.' }
			);
		};
		if ( type === 'adyen' ) {
			scriptNode.onload = setup;
			scriptNode.crossOrigin = 'anonymous';
			scriptNode.integrity = configFromServer.script.integrity;
			scriptNode.src = configFromServer.script.src;
		} else {
			scriptNode.src = configFromServer.googleScript;
		}

		document.body.append( scriptNode );
	}

	$( function () {
		if ( payment_method === 'google' ) {
			loadScript( 'google' );
		}
		loadScript( 'adyen' );
	} );
} )( jQuery, mediaWiki );
