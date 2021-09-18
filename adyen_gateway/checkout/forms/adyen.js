/* global AdyenCheckout, Promise */
( function ( $, mw ) {
	// promise objects are for Apple Pay - see comments below
	var checkout, onSubmit, authPromise, submitPromise;

	/**
	 * Get extra configuration values for specific payment types
	 *
	 * @param {string} type Adyen-side name of component type
	 * @return Object
	 */
	function getComponentConfig( type ) {
		var config = {};
		switch ( type ) {
			case 'card':
			case 'ideal':
				// for cc and ideal, additional config is optional
				return config;
			case 'applepay':
				// for applepay, additional config is required
				var amount = {},
					currency = $( '#currency' ).val(),
					amount_value = $( '#amount' ).val(),
					country = $( '#country' ).val();
				amount.currency = currency;
				amount.value = amountInMinorUnits( amount_value, currency );
				config.amount = amount;
				config.countryCode = country;
				config.requiredBillingContactFields = [
					'name',
					'postalAddress'
				];
				config.requiredShippingContactFields = [
					'email'
				];
				// eslint-disable-next-line compat/compat
				authPromise = new Promise( function ( authResolve, authReject ) {
					config.onAuthorized = function ( resolve, reject, event ) {
						var bContact = event.payment.billingContact,
							sContact = event.payment.shippingContact,
							extraData = {};
						extraData.first_name = bContact.givenName;
						extraData.last_name = bContact.familyName;
						extraData.postal_code = bContact.postalCode;
						extraData.state_province = bContact.administrativeArea;
						extraData.city = bContact.locality;
						if ( bContact.addressLines.length > 0 ) {
							extraData.street_address = bContact.addressLines[ 0 ];
						}
						extraData.email = sContact.emailAddress;
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

				return config;
			default:
				throw Error( 'Component type not found' );
		}
	}

	/**
	 * Given an amount in major currency units, e.g. dollars, returns the
	 * amount in minor units for the currency, e.g. cents. For non-fractional
	 * currencies just rounds the amount to the nearest whole number.
	 *
	 * @param {number} amount
	 * @param {string} currency
	 * @returns {number} amount in minor units for specified currency
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
		config.showPayButton = false;
		// Note: onError not set because error highlighting is handled in css.

		return new AdyenCheckout( config );
	}

	/**
	 * Get the name of the Adyen Checkout component to instantiate
	 *
	 * @param {string} paymentMethod our top-level payment method code
	 * @return {string} name of Adyen Checkout component to instantiate
	 */
	function mapPaymentMethodToComponentType( paymentMethod ) {
		switch ( paymentMethod ) {
			case 'cc':
				return 'card';
			case 'rtbt':
			case 'bt':
				return 'ideal';
			case 'apple':
				return 'applepay';
			default:
				throw Error( 'paymentMethod not found' );
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
			case 'diners':
				return 'dc';
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
			default:
				return adyenBrandCode;
		}
	}

	// eslint-disable-next-line compat/compat
	submitPromise = new Promise( function ( submitResolve, submitReject ) {
		onSubmit = function ( state, component ) {
			var extraData = {},
				payment_method;
			// Submit to our server, unless it's Apple Pay, which submits in
			// the onAuthorized handler.
			if ( mw.donationInterface.validation.validate() && state.isValid ) {
				payment_method = $( '#payment_method' ).val();
				switch ( payment_method ) {
					case 'rtbt':
						if ( state.data.paymentMethod.type === 'ideal' ) {
							extraData = {
								// issuer is bank chosen from dropdown
								issuer_id: state.data.paymentMethod.issuer,
								payment_submethod: 'rtbt_ideal'
							};
						}
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
					case 'apple':
						// Resolve the submit promise with the Apple Pay token and bail out - we
						// also need to wait for the onAuthorized event with contact data.
						submitResolve( state.data.paymentMethod.applePayToken );
						return;
				}

				mw.donationInterface.forms.callDonateApi(
					handleApiResult, extraData, 'di_donate_adyen'
				);
			}
		};
	} );

	function handleApiResult( result ) {
		if ( result.isFailed ) {
			document.location.replace( mw.config.get( 'DonationInterfaceFailUrl' ) );
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

		} else if ( result.redirect ) {
			document.location.replace( result.redirect );
		}
	}

	function onAdditionalDetails( state, dropin ) {
		// Handle 3D secure
	}

	$( function () {
		var payment_method,
			component_type,
			component,
			component_config,
			config,
			ui_container_name;

		payment_method = $( '#payment_method' ).val();
		component_type = mapPaymentMethodToComponentType( payment_method );
		ui_container_name = component_type + '-container';

		// Drop in the adyen components placeholder container
		$( '.submethods' ).before(
			'<div id="' + ui_container_name + '" />'
		).before(
			'<div id="action-container" />'
		);

		// TODO: useful comments
		config = mw.config.get( 'adyenConfiguration' );
		checkout = getCheckout( config );
		component_config = getComponentConfig( component_type );
		component = checkout.create( component_type, component_config );

		if ( component_type === 'applepay' ) {
			component.isAvailable().then( function () {
				component.mount( '#' + ui_container_name );
			} ).catch( function () {
				throw Error( 'Apple Pay is not available!' );
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
			component.mount( '#' + ui_container_name );
			// For everything except Apple Pay, show our standard 'Donate' button
			$( '#paymentSubmit' ).show();
			$( '#paymentSubmitBtn' ).on( 'click', function ( evt ) {
				component.submit( evt );
			} );
		}
	} );
} )( jQuery, mediaWiki );
