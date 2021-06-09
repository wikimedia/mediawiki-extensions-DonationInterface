/* global AdyenCheckout */
( function ( $, mw ) {

	/**
	 * TODO: Determine if any component-specific cfg is needed
	 *
	 * @param type
	 * @returns {string}
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
	 * Set up Adyen Checkout
	 *
	 * @param config
	 * @returns AdyenCheckout
	 */
	function getCheckout( config ) {
		config.onSubmit = onSubmit;
		config.onAdditionalDetails = onAdditionalDetails;
		config.onError = onError;
		config.showPayButton = false;
		return new AdyenCheckout( config );
	}

	/**
	 *
	 * @param paymentMethod
	 * @returns string
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
	 *
	 * TODO: should we do this mapping server-side
	 * using SmashPig's ReferenceData?
	 * @param adyenBrandCode
	 * @returns string
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

	function onSubmit( state, dropin ) {
		// Submit to our server
		if ( mw.donationInterface.validation.validate() && state.isValid ) {
			mw.donationInterface.forms.callDonateApi( handleApiResult, {
				// FIXME, add new API params for encrypted data instead of reusing old ones
				card_num: state.data.paymentMethod.encryptedCardNumber,
				expiration: state.data.paymentMethod.encryptedExpiryMonth,
				// HACK HACK HACK, using a totally dumb field here to not touch API yet
				processor_form: state.data.paymentMethod.encryptedExpiryYear,
				cvv: state.data.paymentMethod.encryptedSecurityCode,
				payment_submethod: mapAdyenSubmethod( state.data.paymentMethod.brand )
			} );
		}
	}

	function handleApiResult( data ) {
		// TODO: handle anything except success
		if ( data.redirect ) {
			document.location.replace( data.redirect );
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
			checkout,
			ui_container_name;

		payment_method = $( '#payment_method' ).val();
		component_type = mapPaymentMethodToComponentType( payment_method );
		ui_container_name = component_type + '-container';

		// Drop in the adyen components placeholder container
		$( '.submethods' ).before(
			'<div id="' + ui_container_name + '" />'
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
