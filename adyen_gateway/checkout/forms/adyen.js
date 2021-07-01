/* global AdyenCheckout */
( function ( $, mw ) {
	var checkout;

	/**
	 * TODO: Determine if any component-specific cfg is needed
	 *
	 * @param {string} type Adyen-side name of component type
	 * @return {string}
	 */
	function getComponentConfig( type ) {
		var config = {};
		switch ( type ) {
			// for card and ideal, additional config is optional
			case 'card':
			case 'ideal':
				return config;
			// for applepay, additional config will be required
			case 'applepay':
				return config;
			default:
				throw Error( 'Component type not found' );
		}
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
			case 'ap':
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

	function onSubmit( state, component ) {
		var extraData,
			payment_method;
		// Submit to our server
		if ( mw.donationInterface.validation.validate() && state.isValid ) {
			payment_method = $( '#payment_method' ).val();
			if ( payment_method === 'rtbt' && state.data.paymentMethod.type === 'ideal' ) {
				extraData = {
					// issuer is bank chosen from dropdown
					issuer_id: state.data.paymentMethod.issuer,
					payment_submethod: 'rtbt_ideal'
				};
			} else {
				extraData = {
					encrypted_card_number: state.data.paymentMethod.encryptedCardNumber,
					encrypted_expiry_month: state.data.paymentMethod.encryptedExpiryMonth,
					encrypted_expiry_year: state.data.paymentMethod.encryptedExpiryYear,
					encrypted_security_code: state.data.paymentMethod.encryptedSecurityCode,
					payment_submethod: mapAdyenSubmethod( state.data.paymentMethod.brand )
				};
				if ( state.data.browserInfo ) {
					extraData.color_depth = state.data.browserInfo.colorDepth;
					extraData.java_enabled = state.data.browserInfo.javaEnabled;
					extraData.screen_height = state.data.browserInfo.screenHeight;
					extraData.screen_width = state.data.browserInfo.screenWidth;
					extraData.time_zone_offset = state.data.browserInfo.timeZoneOffset;
				}
			}

			mw.donationInterface.forms.callDonateApi(
				handleApiResult, extraData, 'di_donate_adyen'
			);
		}
	}

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

	function onError( error ) {
		var fieldId = error.fieldType,
			isValid = error.error === '',
			$fieldDiv = $( 'span[data-cse="' + fieldId + '"]' )
				.closest( '.adyen-checkout__input-wrapper' );
		if ( isValid ) {
			$fieldDiv.removeClass( 'errorHighlight' );
		} else {
			$fieldDiv.addClass( 'errorHighlight' );
		}
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
		component = checkout.create( component_type, component_config ).mount( '#' + ui_container_name );

		$( '#paymentSubmit' ).show();
		$( '#paymentSubmitBtn' ).on( 'click', function ( evt ) {
			mw.donationInterface.validation.validate();
			component.submit( evt );
		} );
	} );
} )( jQuery, mediaWiki );
